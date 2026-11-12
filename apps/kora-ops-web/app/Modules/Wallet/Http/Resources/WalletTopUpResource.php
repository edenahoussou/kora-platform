<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletTopUp
 */
class WalletTopUpResource extends JsonResource
{
    /**
     * Expose une recharge Wallet et son paiement provider.
     *
     * @param  Request  $request  Requête courante
     * @return array<string, mixed> Représentation API
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            'payment' => $this->payment === null ? null : self::paymentDetails($this->payment),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function paymentDetails(Payment $payment): array
    {
        $pending = $payment->status === PaymentStatus::Pending;
        $payload = $payment->provider_payload ?? [];
        $widget = $pending && is_array($payload['widget'] ?? null)
            ? array_intersect_key($payload['widget'], array_flip(['key', 'sandbox', 'amount', 'partnerId', 'paymentmethod', 'countries', 'callback', 'phone', 'data']))
            : null;
        $url = $pending ? ($payload['checkout_url'] ?? null) : null;
        $action = match (true) {
            $payment->status === PaymentStatus::Confirmed => 'done',
            ! $pending => 'retry',
            $widget !== null && blank($payment->provider_transaction_id) => 'open_widget',
            $url !== null => 'open_url',
            ($payload['initiation_phase'] ?? null) === 'ready', filled($payment->provider_transaction_id), filled($payment->provider_reference) => 'verify',
            default => 'wait',
        };
        $code = $payment->failure_code;

        return [
            'uuid' => $payment->uuid,
            'status' => $payment->status->value,
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'next_action' => $action,
            'widget' => $action === 'open_widget' ? $widget : null,
            'checkout_url' => $action === 'open_url' ? $url : null,
            'polling_interval_seconds' => 10,
            'failure' => $code === null ? null : [
                'code' => $code,
                'message' => match ($code) {
                    'invalid_payment_details' => 'Vérifiez les coordonnées et le moyen de paiement saisis.',
                    'insufficient_funds' => 'Le solde de votre compte de paiement est insuffisant.',
                    'payment_declined' => 'L’opérateur a refusé le paiement.',
                    default => $pending ? 'Le paiement est en cours de vérification.' : 'Impossible de démarrer le paiement. Vous pouvez réessayer.',
                },
                'retryable' => ! $pending,
                'diagnostic_reference' => $payment->uuid,
            ],
        ];
    }
}
