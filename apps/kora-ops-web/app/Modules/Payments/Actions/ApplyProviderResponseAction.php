<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Actions\ReconcileWalletTopUpAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies an already-verified provider response to the correct business flow.
 *
 * Every caller must fetch the authoritative status server-side first. Provider
 * webhook payloads are signals, never proof.
 */
final class ApplyProviderResponseAction
{
    public function __construct(
        private readonly ConfirmPaymentAction $confirmPayment,
    ) {}

    /**
     * @param  array<string, mixed>  $authenticatedEvent
     */
    public function handle(
        Payment $payment,
        ProviderResponse $response,
        array $authenticatedEvent = [],
        ?string $authenticatedTransactionId = null,
    ): Payment {
        if ($payment->parcel_charge_id !== null) {
            return app(ParcelPayments::class)->confirmResponse($payment, $response, $authenticatedEvent);
        }

        if ($payment->reservation_id === null) {
            return app(ReconcileWalletTopUpAction::class)->applyResponse($payment, $response, $authenticatedEvent);
        }

        $inconsistentPending = $response->isPending()
            && filled($response->payload['verification_issue'] ?? null);

        $isMtn = $payment->provider === 'mtn_momo';
        $candidateTransactionId = $isMtn
            ? $response->providerTransactionId
            : ($authenticatedTransactionId ?? $response->providerTransactionId);

        if (! $inconsistentPending
            && $isMtn
            && $response->providerTransactionId !== null
            && $payment->provider_transaction_id !== null
            && ! hash_equals($payment->provider_transaction_id, $response->providerTransactionId)) {
            Log::critical('MTN MoMo transaction id conflict', [
                'payment_uuid' => $payment->uuid,
                'existing_transaction_id' => $payment->provider_transaction_id,
                'provider_transaction_id' => $response->providerTransactionId,
            ]);

            return $payment->refresh();
        }

        $shouldPersistTransactionId = ! $inconsistentPending
            && $candidateTransactionId !== null
            && $payment->provider_transaction_id === null
            && ! $payment->status->isTerminal()
            && ($isMtn || ! $response->isPending());

        if ($shouldPersistTransactionId) {
            DB::transaction(function () use ($payment, $candidateTransactionId): void {
                $locked = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($locked === null
                    || $locked->provider_transaction_id !== null
                    || $locked->status->isTerminal()) {
                    return;
                }

                $locked->update(['provider_transaction_id' => $candidateTransactionId]);
            });

            $payment->refresh();
        }

        $status = match ($response->status) {
            PaymentStatus::Confirmed => 'confirmed',
            PaymentStatus::Pending => 'pending',
            PaymentStatus::Cancelled => 'cancelled',
            default => 'failed',
        };

        return $this->confirmPayment->handle(
            payment: $payment,
            status: $status,
            providerReference: $response->providerReference,
            providerPayload: $response->payload,
        );
    }
}
