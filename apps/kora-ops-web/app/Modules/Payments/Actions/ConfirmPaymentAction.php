<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentAction
{
    /**
     * @param  array<string, mixed>  $providerPayload
     */
    public function handle(
        Payment $payment,
        string $status,
        ?string $providerReference = null,
        array $providerPayload = [],
    ): Payment {
        return DB::transaction(function () use ($payment, $status, $providerReference, $providerPayload): Payment {
            $payment->loadMissing(['reservation.segments']);

            $isSuccess = in_array(strtolower($status), ['success', 'confirmed'], true);
<<<<<<< HEAD
            $isPending = strtolower($status) === PaymentStatus::Pending->value;
            $mergedPayload = array_merge($lockedPayment->provider_payload ?? [], $providerPayload);
=======
>>>>>>> 752c4ce (feat: implement bus amenity management system)

            $mergedPayload = array_merge($payment->provider_payload ?? [], $providerPayload);

            if ($isSuccess) {
                $payment->update([
                    'status' => PaymentStatus::Confirmed,
                    'confirmed_at' => now(),
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);

<<<<<<< HEAD
                $reservation->update(['status' => ReservationStatus::Paid]);
                $reservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);
                $reservation->update(['ticket_issuance_status' => 'pending', 'ticket_issuance_error' => null]);
                IssueReservationTicketsJob::dispatch($reservation->getKey())->afterCommit();
            } elseif ($isPending) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Pending,
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);
            } else {
                $lockedPayment->update([
                    'status' => strtolower($status) === PaymentStatus::Cancelled->value
                        ? PaymentStatus::Cancelled
                        : PaymentStatus::Failed,
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
=======
                $reservation = $payment->reservation;
                if ($reservation) {
                    $reservation->update(['status' => ReservationStatus::Paid]);
                    $reservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);
                }
            } else {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
>>>>>>> 752c4ce (feat: implement bus amenity management system)
                    'provider_payload' => $mergedPayload,
                ]);

                $reservation = $payment->reservation;
                if ($reservation) {
                    $reservation->update(['status' => ReservationStatus::Expired]);
                    $reservation->segments()->update([
                        'status' => ReservationSegmentStatus::Expired,
                        'released_at' => now(),
                    ]);
                }
            }

            return $payment->fresh(['reservation.departure']);
        });
    }
}
