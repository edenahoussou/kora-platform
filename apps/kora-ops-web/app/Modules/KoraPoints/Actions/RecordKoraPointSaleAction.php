<?php

namespace App\Modules\KoraPoints\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointLedgerService;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordKoraPointSaleAction
{
    public function __construct(private readonly KoraPointTermsService $terms, private readonly KoraPointLedgerService $ledger, private readonly KoraPointAccess $access) {}

    /**
     * Records a partner sale for a confirmed counter payment; returns null when the seller has no point.
     */
    public function execute(Reservation $reservation, Payment $payment, User $seller): ?KoraPointSale
    {
        return DB::transaction(fn (): ?KoraPointSale => $this->record($reservation, $payment, $seller), 3);
    }

    /**
     * Applies the partner sale invariants then writes the sale and its ledger entry.
     */
    private function record(Reservation $reservation, Payment $payment, User $seller): ?KoraPointSale
    {
        $point = KoraPoint::query()->where('agency_id', $seller->agency_id ?? 0)->lockForUpdate()->first();
        if ($point === null) {
            if ($this->access->isPartner($seller)) {
                throw ValidationException::withMessages(['point' => 'Aucun Point de vente affecté.']);
            }

            return null;
        }
        $this->access->authorize($seller, 'counter-sales.create');
        if ($payment->status !== PaymentStatus::Confirmed || $reservation->status !== ReservationStatus::Paid || $payment->reservation_id !== $reservation->getKey() || $payment->amount !== (int) $reservation->total_amount || $payment->currency !== 'XOF') {
            throw ValidationException::withMessages(['payment' => 'Une vente partenaire exige son paiement confirmé en XOF.']);
        }
        if ($reservation->origin_kora_point_id !== $point->getKey()) {
            throw ValidationException::withMessages(['point' => 'La réservation provient d’un autre Point ; recréez la vente après réaffectation.']);
        }
        $existing = KoraPointSale::query()->where('payment_id', $payment->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }
        if (! $seller->hasRole('kora_point_agent') || $point->status !== 'active' || $point->agency->status->value !== 'active' || ($point->organization !== null && $point->organization->status !== 'active')) {
            throw ValidationException::withMessages(['point' => 'Ce Point ne peut pas effectuer de nouvelles ventes.']);
        }
        if (! in_array($payment->method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true) || $reservation->currency !== 'XOF' || $reservation->company_account_id !== null) {
            throw ValidationException::withMessages(['method' => 'Le Point encaisse uniquement les ventes individuelles en espèces ou Mobile Money XOF.']);
        }
        $terms = $this->terms->required($point);
        $gross = (int) $payment->amount;
        $commission = intdiv($gross * (int) $terms['commission_basis_points'] + 5000, 10000);
        $net = $gross - $commission;
        $overdue = $point->sales()->where('outstanding_amount', '>', 0)->where('due_at', '<=', now()->subDays($this->terms->graceDays()))->exists();
        if ($overdue || $this->ledger->balance($point) + $net > $terms['ceiling_amount']) {
            throw ValidationException::withMessages(['point' => 'Encours maximum ou échéance de reversement dépassé.']);
        }
        $paidAt = CarbonImmutable::instance($payment->confirmed_at ?? now());
        $dueAt = $this->terms->dueAt($terms['settlement_calendar'], $paidAt);
        $sale = KoraPointSale::query()->create([
            'point_id' => $point->getKey(), 'organization_id' => $point->organization_id,
            'reservation_id' => $reservation->getKey(), 'payment_id' => $payment->getKey(), 'seller_id' => $seller->getKey(),
            'status' => 'open', 'gross_amount' => $gross, 'commission_amount' => $commission, 'net_amount' => $net, 'outstanding_amount' => 0,
            'terms_snapshot' => $terms + ['initial_due_at' => $dueAt->toIso8601String()], 'paid_at' => $paidAt, 'due_at' => $dueAt,
        ]);
        $this->ledger->post($sale, $seller, 'sale:'.$payment->uuid, 'sale', $net, $commission);

        return $sale;
    }
}
