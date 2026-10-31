<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\Booking\Actions\CancelReservationAction;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Data\PaymentRefundDestination;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Models\PaymentRefund;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoraPointRefundService
{
    public function __construct(private readonly KoraPointAccess $access, private readonly CancelReservationAction $cancel, private readonly KoraPointLedgerService $ledger, private readonly KoraPointTermsService $terms) {}

    public function request(User $actor, KoraPointSale $sale, string $reason, ?string $passengerUuid = null): KoraPointRefund
    {
        $this->access->authorize($actor, 'kora-points.refund-request');
        $this->access->requirePoint($actor, $sale->point);

        return DB::transaction(function () use ($actor, $sale, $reason, $passengerUuid): KoraPointRefund {
            $locked = $this->lockSale($sale);
            if ($locked->refunds()->whereNotIn('status', ['confirmed', 'rejected'])->exists() || $locked->refunded_amount >= $locked->gross_amount) {
                throw ValidationException::withMessages(['refund' => 'Remboursement déjà en cours ou vente remboursée.']);
            }
            $passenger = $passengerUuid === null ? null : $locked->reservation->passengers()->where('uuid', $passengerUuid)->where('status', 'active')->firstOrFail();
            $refund = $locked->refunds()->create(['requested_by' => $actor->getKey(), 'reason' => $reason, 'passenger_id' => $passenger?->getKey(), 'status' => 'requested']);
            $this->audit($actor, $refund, 'refund.requested');

            return $refund;
        }, 3);
    }

    public function authorize(User $actor, KoraPointRefund $refund, bool $reject = false): void
    {
        $this->access->authorize($actor, 'kora-points.finance');
        DB::transaction(function () use ($actor, $refund, $reject): void {
            $sale = $this->lockSale($refund->sale);
            $locked = KoraPointRefund::query()->lockForUpdate()->whereKey($refund->getKey())->firstOrFail();
            if ($locked->status === 'authorized' && $reject) {
                $locked->update(['status' => 'rejected', 'authorized_by' => $actor->getKey()]);
                $locked->paymentRefund?->update(['status' => PaymentRefundStatus::Failed, 'idempotency_key' => $locked->paymentRefund->idempotency_key.':voided:'.$locked->uuid, 'reference' => 'REF-'.strtoupper(substr(hash('sha256', $locked->paymentRefund->idempotency_key.':voided:'.$locked->uuid), 0, 20))]);
                $this->audit($actor, $locked, 'refund.rejected_authorized');

                return;
            }
            if ($locked->status === 'authorized') {
                return;
            }
            if ($locked->status !== 'requested') {
                throw ValidationException::withMessages(['refund' => 'Cette demande a déjà été traitée.']);
            }
            if ($reject) {
                $locked->update(['status' => 'rejected', 'authorized_by' => $actor->getKey()]);
            } else {
                $remaining = $sale->gross_amount - (int) $sale->payment->refunds()->whereNotIn('status', [PaymentRefundStatus::Failed->value])->sum('amount');
                $allocation = $locked->passenger_id === null ? $remaining : (int) ($sale->reservation->total_snapshot['passenger_allocations'][$sale->reservation->passengers()->whereKey($locked->passenger_id)->firstOrFail()->passenger_index ?? 0] ?? 0);
                if ($allocation <= 0 || $allocation > $remaining) {
                    throw ValidationException::withMessages(['refund' => 'Le montant remboursable doit être positif et ne pas dépasser le solde payé.']);
                }
                if ($locked->passenger_id === null) {
                    $this->cancel->cancel($sale->reservation, $actor, true);
                    $scope = 'reservation:'.$sale->reservation->uuid.':full';
                } else {
                    $passenger = $sale->reservation->passengers()->whereKey($locked->passenger_id)->where('status', 'active')->firstOrFail();
                    $this->cancel->cancelPassenger($sale->reservation, $passenger, $actor, true);
                    $scope = 'reservation:'.$sale->reservation->uuid.':passenger:'.$passenger->uuid;
                }
                $paymentRefund = PaymentRefund::query()->where('idempotency_key', $scope)->firstOrFail();
                $executor = $sale->settled_at === null ? 'point' : 'kora';
                $paymentRefund->update(['destination' => $executor === 'point' ? PaymentRefundDestination::PartnerManual : PaymentRefundDestination::KoraManual]);
                $locked->update(['status' => 'authorized', 'authorized_by' => $actor->getKey(), 'amount' => $paymentRefund->amount, 'payment_refund_id' => $paymentRefund->getKey(), 'executor' => $executor]);
            }
            $this->audit($actor, $locked, 'refund.'.$locked->status);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function execute(User $actor, KoraPointRefund $refund, array $data): void
    {
        DB::transaction(function () use ($actor, $refund, $data): void {
            $sale = $this->lockSale($refund->sale);
            $locked = KoraPointRefund::query()->lockForUpdate()->whereKey($refund->getKey())->firstOrFail();
            $this->requireExecutor($actor, $locked);
            if ($locked->status === 'executed') {
                if ($locked->reference === $data['reference'] && $locked->method === $data['method']) {
                    return;
                }
                throw ValidationException::withMessages(['refund' => 'La restitution a déjà été déclarée avec un autre contenu.']);
            }
            if ($locked->status !== 'authorized') {
                throw ValidationException::withMessages(['refund' => 'Autorisation de remboursement requise.']);
            }
            $beneficiary = $sale->payment->payer_phone ?: ($sale->payment->payer_name ?: $sale->reservation->payer?->phone);
            if (blank($beneficiary)) {
                throw ValidationException::withMessages(['beneficiary' => 'Payeur initial non identifié sur le paiement source.']);
            }
            if ($data['method'] !== $sale->payment->method->value) {
                throw ValidationException::withMessages(['method' => 'Utilisez le moyen de paiement initial.']);
            }
            if (! KoraPartnerDocument::query()->where('refund_id', $locked->getKey())->whereHas('media')->exists()) {
                throw ValidationException::withMessages(['proof' => 'Joignez la preuve de restitution.']);
            }
            $locked->update(['status' => 'executed', 'executed_by' => $actor->getKey(), 'executed_at' => now(), 'method' => $data['method'], 'reference' => $data['reference'], 'beneficiary' => $beneficiary]);
            $this->audit($actor, $locked, 'refund.executed');
        }, 3);
    }

    public function confirm(User $actor, KoraPointRefund $refund): void
    {
        $this->access->authorize($actor, 'kora-points.finance');
        DB::transaction(function () use ($actor, $refund): void {
            $sale = $this->lockSale($refund->sale);
            $locked = KoraPointRefund::query()->lockForUpdate()->whereKey($refund->getKey())->firstOrFail();
            if ($locked->status === 'confirmed') {
                return;
            }
            if ($locked->status !== 'executed') {
                throw ValidationException::withMessages(['refund' => 'Une restitution effective doit être déclarée.']);
            }
            $refunded = $sale->refunded_amount + $locked->amount;
            if ($refunded > $sale->gross_amount) {
                throw ValidationException::withMessages(['refund' => 'Le cumul dépasse le paiement initial.']);
            }
            $cumulative = $sale->gross_amount === 0 ? 0 : min($sale->commission_amount, intdiv($sale->commission_amount * $refunded + intdiv($sale->gross_amount, 2), $sale->gross_amount));
            $reversal = $cumulative - $sale->commission_reversed;
            $delta = $locked->executor === 'point' ? -$locked->amount + $reversal : $reversal;
            $this->ledger->post($sale, $actor, 'refund:'.$locked->uuid, 'refund', $delta, -$reversal);
            $outstanding = (int) $sale->refresh()->outstanding_amount;
            $sale->update(['refunded_amount' => $refunded, 'commission_reversed' => $cumulative, 'status' => $outstanding > 0 ? ($sale->settled_at === null ? 'open' : 'reopened') : 'settled']);
            if ($locked->executor === 'kora' && $outstanding > 0) {
                $sale->update(['due_at' => $this->terms->dueAt($sale->terms_snapshot['settlement_calendar'], CarbonImmutable::now())]);
            }
            $locked->paymentRefund->update(['status' => PaymentRefundStatus::Completed, 'completed_at' => now()]);
            $locked->update(['status' => 'confirmed', 'confirmed_by' => $actor->getKey(), 'confirmed_at' => now()]);
            $this->audit($actor, $locked, 'refund.confirmed');
        }, 3);
    }

    public function requireExecutor(User $actor, KoraPointRefund $refund): void
    {
        if ($refund->executor === 'point') {
            $this->access->authorize($actor, 'kora-points.refund-request');
            $this->access->requirePoint($actor, $refund->sale->point);
            if (! $actor->hasRole('kora_point_agent') || $actor->agency_id !== $refund->sale->point->agency_id) {
                throw new AuthorizationException;
            }
        } else {
            $this->access->authorize($actor, 'kora-points.finance');
        }
    }

    private function lockSale(KoraPointSale $sale): KoraPointSale
    {
        Reservation::query()->whereKey($sale->reservation_id)->lockForUpdate()->firstOrFail();
        KoraPoint::query()->whereKey($sale->point_id)->lockForUpdate()->firstOrFail();
        $sale = KoraPointSale::query()->lockForUpdate()->whereKey($sale->getKey())->firstOrFail();
        if (KoraPointRemittanceLine::query()->where('claimed_sale_id', $sale->getKey())->exists()) {
            throw ValidationException::withMessages(['refund' => 'Rapprochez ou retirez le bordereau en cours avant le remboursement.']);
        }

        return $sale;
    }

    private function audit(User $actor, KoraPointRefund $refund, string $event): void
    {
        activity('kora-points')->performedOn($refund)->causedBy($actor)->withProperties(['amount' => $refund->amount, 'executor' => $refund->executor])->log($event);
    }
}
