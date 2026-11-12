<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

            Gate::forUser($user)->authorize('view', $lockedPayment);

            if ($lockedPayment->reservation_id === null) {
                throw ValidationException::withMessages(['payment' => 'Une recharge doit être vérifiée auprès de l’opérateur avant de pouvoir être clôturée.']);
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
