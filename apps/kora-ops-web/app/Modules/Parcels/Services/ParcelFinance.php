<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Fiscalization\Actions\InitiateCreditNoteAction;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentRefundDestination;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

class ParcelFinance
{
    public function __construct(private ParcelAccess $access, private WalletLedgerService $walletLedger) {}

    public function invoice(Parcel $parcel, ParcelCharge $charge, Payment $payment): void
    {
        $invoice = FiscalInvoice::query()->create([
            'parcel_charge_id' => $charge->id,
            'payment_id' => $payment->id,
            'kora_reference' => $charge->return_leg ? $parcel->reference.'-R' : $parcel->reference,
            'document_kind' => FiscalInvoiceDocumentKind::Sale,
            'status' => FiscalInvoiceStatus::Pending,
            'total_without_tax' => $charge->net,
            'total_tax' => $charge->tax,
            'total_with_tax' => $charge->total,
            'tax_snapshot' => ['parcel' => ['tax_group' => $charge->fiscal_group, 'rate' => $charge->tax_rate, 'code' => $charge->tax_code]],
        ]);

        FiscalizeParcelInvoiceJob::dispatch($invoice->id)->afterCommit();
    }

    public function authorizeRefund(Parcel $parcel, Payment $payment, User $actor, int $amount, string $reason, string $key): PaymentRefund
    {
        $this->access->require($actor, 'parcels.finance');

        return DB::transaction(function () use ($parcel, $payment, $actor, $amount, $reason, $key): PaymentRefund {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $charge = $parcel->charges()->findOrFail($payment->parcel_charge_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $existing = $payment->refunds()->where('idempotency_key', 'parcel:'.$key)->first();
            if ($existing !== null) {
                abort_unless($existing->amount === $amount && ($existing->metadata['reason'] ?? null) === $reason, 409);

                return $existing;
            }
            abort_unless($payment->status === PaymentStatus::Confirmed, 409);
            $late = $charge->settled_payment_id !== $payment->id;
            $funding = WalletPurchaseFunding::fromPayment($payment);
            $maximum = $late ? $funding->providerAmount : $payment->amount;
            $beforeTransport = ! $charge->return_leg && ! $late && $parcel->status === 'registered';
            abort_if($beforeTransport && $amount !== $maximum, 422, 'Before hub validation, the refund must be in full.');
            abort_if($payment->refunds()->where('status', PaymentRefundStatus::Pending)->exists(), 409);
            $already = (int) $payment->refunds()->where('status', PaymentRefundStatus::Completed)->sum('amount');
            abort_if($amount < 1 || $amount > $maximum - $already, 422);
            $fiscalQuantity = $late ? null : round(($already + $amount) / $payment->amount, 3) - round($already / $payment->amount, 3);
            abort_if($fiscalQuantity !== null && (int) round($fiscalQuantity * $payment->amount) !== $amount, 422,
                'Ce montant partiel ne peut pas être représenté exactement dans l’avoir fiscal. Ajustez le montant.');
            $refund = $payment->refunds()->create([
                'amount' => $amount, 'currency' => app(GeneralSettings::class)->currency, 'destination' => PaymentRefundDestination::KoraManual,
                'status' => PaymentRefundStatus::Pending, 'reference' => 'COLIS-REST-'.$key, 'idempotency_key' => 'parcel:'.$key,
                'metadata' => ['reason' => $reason, 'authorized_by' => $actor->id, 'authorized_at' => now()->toIso8601String(), 'fiscal_quantity' => $fiscalQuantity],
            ]);
            if ($beforeTransport) {
                $parcel->update(['status' => 'cancellation_pending']);
            }
            $parcel->record('refund_authorized', $actor, details: ['refund_uuid' => $refund->uuid, 'reason' => $reason]);

            return $refund;
        });
    }

    public function completeRefund(Parcel $parcel, PaymentRefund $refund, User $actor, string $proof, ?string $cashLedgerUuid): PaymentRefund
    {
        $this->access->require($actor, 'parcels.finance');

        return DB::transaction(function () use ($parcel, $refund, $actor, $proof, $cashLedgerUuid): PaymentRefund {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $charge = $parcel->charges()->findOrFail($payment->parcel_charge_id);
            $refund = $payment->refunds()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status === PaymentRefundStatus::Completed) {
                return $refund;
            }
            abort_unless($refund->status === PaymentRefundStatus::Pending && $payment->status === PaymentStatus::Confirmed, 409);
            $already = (int) $payment->refunds()->where('status', PaymentRefundStatus::Completed)->sum('amount');
            $cumulative = $already + $refund->amount;
            $funding = WalletPurchaseFunding::fromPayment($payment);
            $settled = $charge->settled_payment_id === $payment->id;

            $cash = 0;
            $points = 0;
            if ($settled) {
                $walletId = WalletHold::query()->where('payment_id', $payment->id)->value('wallet_id');
                $wallet = $walletId !== null
                    ? Wallet::query()->findOrFail((int) $walletId)
                    : Wallet::query()->where('user_id', $parcel->sender_user_id)->first();
                if ($wallet !== null) {
                    $cash = intdiv($funding->walletCash * $cumulative, $payment->amount) - intdiv($funding->walletCash * $already, $payment->amount);
                    $points = intdiv($funding->points * $cumulative, $payment->amount) - intdiv($funding->points * $already, $payment->amount);
                    if ($cash > 0) {
                        $this->walletLedger->credit($wallet, WalletBalanceType::Cash, $cash, WalletTransactionReason::RefundCash, $refund->uuid.':cash', reference: $refund);
                    }
                    if ($points > 0) {
                        $this->walletLedger->credit($wallet, WalletBalanceType::Points, $points, WalletTransactionReason::RefundPoints, $refund->uuid.':points', reference: $refund);
                    }
                    $earned = (int) WalletTransaction::query()->where('idempotency_key', 'parcel:'.$payment->uuid.':earn')->value('amount');
                    $correction = intdiv($earned * $cumulative, $payment->amount) - intdiv($earned * $already, $payment->amount);
                    $this->walletLedger->correctPoints($wallet, $correction, $refund->uuid.':correction', reference: $refund, actor: $actor);
                }
            }
            $externalAmount = $refund->amount - $cash - $points * $funding->pointRate;
            if ($payment->method === PaymentMethod::Cash && $externalAmount > 0) {
                abort_if($cashLedgerUuid === null, 422, 'Select the cash sub-ledger that executed the refund.');
                $this->postCashRefund($parcel, $refund, $actor, $cashLedgerUuid, $externalAmount);
            }
            $refund->update([
                'status' => PaymentRefundStatus::Completed, 'completed_at' => now(),
                'metadata' => array_replace($refund->metadata ?? [], [
                    'executed_by' => $actor->id, 'proof' => $proof, 'wallet_cash' => $cash, 'points' => $points,
                    'external_amount' => $externalAmount,
                ]),
            ]);
            if ($cumulative === ($settled ? $payment->amount : $funding->providerAmount)) {
                $payment->update(['status' => PaymentStatus::Refunded]);
            }
            if ($parcel->status === 'cancellation_pending') {
                $parcel->update(['status' => 'cancelled', 'custody_hub_id' => null]);
            }
            $parcel->record('refund_completed', $actor, details: ['refund_uuid' => $refund->uuid]);

            return $refund;
        });
    }

    private function postCashRefund(Parcel $parcel, PaymentRefund $refund, User $actor, string $ledgerUuid, int $amount): void
    {
        $ledger = CashLedger::query()->where('uuid', $ledgerUuid)->where('kind', CashLedgerKind::Cash)->firstOrFail();
        $session = CashSession::query()->whereKey($ledger->cash_session_id)
            ->where('agency_id', $parcel->corridor?->originHub?->agency_id)
            ->where('status', CashSessionStatus::Open)->lockForUpdate()->firstOrFail();
        $ledger = CashLedger::query()->whereKey($ledger->id)->where('status', CashLedgerStatus::Open)->lockForUpdate()->firstOrFail();
        abort_if($ledger->expected_balance < $amount, 409, 'The theoretical cash balance is insufficient.');
        CashMovement::query()->create([
            'cash_session_id' => $session->id, 'cash_register_id' => $session->cash_register_id, 'cash_ledger_id' => $ledger->id,
            'source_type' => PaymentRefund::class, 'source_id' => $refund->id, 'type' => 'parcel_refund',
            'direction' => CashMovementDirection::Out, 'status' => CashMovementStatus::Posted,
            'amount' => $amount, 'currency' => app(GeneralSettings::class)->currency, 'description' => 'Restitution KORA Colis',
            'reference' => $refund->reference, 'created_by' => $actor->id, 'occurred_at' => now(),
        ]);
        $ledger->decrement('expected_balance', $amount);
        $session->decrement('expected_balance', $amount);
    }

    public function retryFiscalDocuments(InitiateCreditNoteAction $creditNotes): void
    {
        $alertHours = app(OperationsSettings::class)->parcelFiscalAlertHours;
        FiscalInvoice::query()->whereNotNull('parcel_charge_id')->where('document_kind', FiscalInvoiceDocumentKind::Sale)
            ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
            ->where('created_at', '<=', now()->subHours($alertHours))
            ->eachById(fn (FiscalInvoice $invoice) => app(OpsNotifier::class)->fiscalizationFailed($invoice));

        FiscalInvoice::query()->whereNotNull('parcel_charge_id')->where('document_kind', FiscalInvoiceDocumentKind::Sale)
            ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->eachById(fn (FiscalInvoice $invoice) => FiscalizeParcelInvoiceJob::dispatch($invoice->id));

        PaymentRefund::query()->where('status', PaymentRefundStatus::Completed)
            ->whereHas('payment', fn ($query) => $query->whereNotNull('parcel_charge_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('fiscal_invoices')->whereColumn('credit_note_idempotency_key', 'payment_refunds.uuid'))
            ->with('payment')->eachById(function (PaymentRefund $refund) use ($creditNotes): void {
                $payment = $refund->payment;
                $invoice = FiscalInvoice::query()->where('payment_id', $payment?->id)->where('document_kind', FiscalInvoiceDocumentKind::Sale)
                    ->where('status', FiscalInvoiceStatus::Certified)->first();
                $actor = User::query()->find((int) ($refund->metadata['executed_by'] ?? 0));
                if ($invoice === null || $actor === null || $payment === null) {
                    return;
                }

                try {
                    $creditNotes->execute($invoice, [
                        'mode' => $refund->amount === $payment->amount ? 'full' : 'partial',
                        'reason' => (string) $refund->metadata['reason'],
                        'idempotency_key' => $refund->uuid,
                        'lines' => [['key' => 'line-0', 'quantity' => (float) $refund->metadata['fiscal_quantity']]],
                    ], $actor);
                } catch (\DomainException $exception) {
                    report($exception);
                }
            });
    }
}
