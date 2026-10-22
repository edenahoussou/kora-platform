<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class VerifyPaymentAction
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly ConfirmPaymentAction $confirmPayment,
    ) {}

    public function handle(Payment $payment, User $user, ?string $providerTransactionId = null): Payment
    {
        $payment->loadMissing('reservation');

        if ($payment->reservation?->created_by_user_id !== null
            && $payment->reservation->created_by_user_id !== $user->getKey()
            && $payment->reservation->payer_user_id !== $user->getKey()) {
            throw new AuthorizationException('Vous n’avez pas accès à ce paiement.');
        }

        if ($payment->status->isTerminal()) {
            return $payment->fresh(['reservation.segments.departure']);
        }

        if ($providerTransactionId !== null && $payment->provider === 'kkiapay') {
            if ($payment->provider_transaction_id === null) {
                $payment->update(['provider_transaction_id' => $providerTransactionId]);
            } elseif ($payment->provider_transaction_id !== $providerTransactionId) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Cette transaction ne correspond pas au paiement en cours.',
                ]);
            }
        }

        $response = $this->manager->resolve($payment->provider)->verify($payment);
        $updated = $this->confirmPayment->handle(
            payment: $payment,
            status: $response->status->value,
            providerReference: $response->providerReference,
            providerPayload: $response->payload,
        );

        $updated->update(['last_verified_at' => now()]);

        return $updated->fresh(['reservation.segments.departure']);
    }
}
