<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Payments\Models\Payment;

class PassengerPaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        if ($payment->reservation_id === null) {
            return $payment->walletTopUp?->wallet?->user_id === $user->getKey();
        }

        return $payment->reservation?->created_by_user_id === $user->getKey()
            || $payment->reservation?->payer_user_id === $user->getKey();
    }
}
