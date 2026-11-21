<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Actions\ReconcileWalletTopUpAction;
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
    ): Payment {
        if ($payment->parcel_charge_id !== null) {
            return app(ParcelPayments::class)->confirmResponse($payment, $response, $authenticatedEvent);
        }

        if ($payment->reservation_id === null) {
            return app(ReconcileWalletTopUpAction::class)->applyResponse($payment, $response, $authenticatedEvent);
        }

        if ($payment->provider === 'mtn_momo'
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

        if ($payment->provider === 'mtn_momo'
            && $response->providerTransactionId !== null
            && $payment->provider_transaction_id === null) {
            $payment->update(['provider_transaction_id' => $response->providerTransactionId]);
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
