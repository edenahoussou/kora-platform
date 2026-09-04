<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Auth\Access\AuthorizationException;

class VerifyPaymentAction
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly ConfirmPaymentAction $confirmPayment,
    ) {}

    public function handle(Payment $payment, User $user): Payment
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
