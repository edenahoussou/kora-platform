<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
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
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $reservation = Reservation::query()
                ->whereKey($lockedPayment->reservation_id)
                ->lockForUpdate()
                ->firstOrFail();

            $isSuccess = in_array(strtolower($status), ['success', 'confirmed'], true);
            $isPending = strtolower($status) === PaymentStatus::Pending->value;
            $mergedPayload = array_merge($lockedPayment->provider_payload ?? [], $providerPayload);

            if ($lockedPayment->status === PaymentStatus::Confirmed ||
                ($lockedPayment->status === PaymentStatus::Failed && $isSuccess)) {
                return $lockedPayment->fresh(['reservation.departure']);
            }

            if ($isSuccess && $this->isNotPayable($reservation)) {
                $mergedPayload['rejection_reason'] = $reservation->status === ReservationStatus::Cancelled
                    ? 'reservation_cancelled'
                    : 'reservation_expired';
                $this->expireReservation($reservation);

                $lockedPayment->update([
                    'status' => PaymentStatus::Failed,
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);
            } elseif ($isSuccess) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Confirmed,
                    'confirmed_at' => now(),
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);

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
                    'provider_payload' => $mergedPayload,
                ]);

                $this->expireReservation($reservation);
            }

            return $lockedPayment->fresh(['reservation.departure']);
        });
    }

    private function isNotPayable(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::Expired
            || $reservation->status === ReservationStatus::Cancelled
            || (($reservation->status === ReservationStatus::Held || $reservation->status === ReservationStatus::Confirmed)
                && $reservation->expires_at?->isPast());
    }

    private function expireReservation(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Held && $reservation->status !== ReservationStatus::Confirmed) {
            return;
        }

        $reservation->update(['status' => ReservationStatus::Expired]);
        $reservation->segments()
            ->whereIn('status', [
                ReservationSegmentStatus::Held->value,
                ReservationSegmentStatus::Confirmed->value,
            ])
            ->update([
                'status' => ReservationSegmentStatus::Expired,
                'released_at' => now(),
            ]);
    }
}
