<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPaymentAction
{
    public function handle(Payment $payment, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $user): Payment {
            $lockedPayment = Payment::query()
                ->with('reservation')
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->reservation?->user_id !== null && $lockedPayment->reservation->user_id !== $user->getKey()) {
                throw new AuthorizationException('Vous n’avez pas accès à ce paiement.');
            }

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'payment' => 'Seul un paiement en attente peut être annulé.',
                ]);
            }

            $lockedPayment->update(['status' => PaymentStatus::Cancelled]);

            return $lockedPayment->fresh(['reservation.segments.departure']);
        });
    }
}
