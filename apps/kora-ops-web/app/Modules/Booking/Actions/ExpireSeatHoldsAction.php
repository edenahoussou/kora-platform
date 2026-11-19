<?php

namespace App\Modules\Booking\Actions;

use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Payments\Data\PaymentStatus;
use Illuminate\Support\Facades\DB;

class ExpireSeatHoldsAction
{
    public function __construct(private readonly CompanyCreditService $companyCredit) {}

    /**
     * Expire all held reservations whose expiration timestamp is in the past.
     *
     * @return int Number of expired reservations.
     */
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $expiredReservations = Reservation::query()
                ->where('status', ReservationStatus::Held->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->get();

            $count = 0;

            foreach ($expiredReservations as $reservation) {
                $lockedReservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedReservation->status !== ReservationStatus::Held || $lockedReservation->expires_at?->isFuture()) {
                    continue;
                }
                $assistance = AssistedBooking::query()
                    ->where('reservation_id', $lockedReservation->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($assistance?->status === AssistedBookingStatus::InProgress) {
                    $lockedReservation->update([
                        'status' => ReservationStatus::AssistancePending,
                        'draft_data' => null,
                        'expires_at' => $assistance->expires_at,
                    ]);
                    $lockedReservation->segments()
                        ->where('status', ReservationSegmentStatus::Held->value)
                        ->update([
                            'status' => ReservationSegmentStatus::Requested,
                            'released_at' => now(),
                        ]);
                    $this->companyCredit->release($lockedReservation);
                    $assistance->update([
                        'status' => AssistedBookingStatus::Pending,
                        'assigned_user_id' => null,
                        'released_at' => now(),
                        'reason_code' => 'seat_hold_expired',
                        'reason_note' => 'Le blocage de sièges du guichet a expiré.',
                    ]);
                    activity('assisted-bookings')
                        ->event('released')
                        ->performedOn($assistance)
                        ->withProperties([
                            'reservation_reference' => $lockedReservation->reference,
                            'reason' => 'seat_hold_expired',
                        ])
                        ->log('assisted_booking.released');
                    $count++;

                    continue;
                }

                $lockedReservation->update([
                    'status' => ReservationStatus::Expired,
                    'draft_data' => null,
                ]);

                $lockedReservation->payments()
                    ->where('status', PaymentStatus::Pending->value)
                    ->update([
                        'status' => PaymentStatus::Failed,
                        'failure_code' => 'reservation_expired',
                        'failure_message' => 'La réservation a expiré avant la confirmation du paiement.',
                    ]);

                $lockedReservation->segments()
                    ->where('status', ReservationSegmentStatus::Held->value)
                    ->update([
                        'status' => ReservationSegmentStatus::Expired,
                        'released_at' => now(),
                    ]);
                $this->companyCredit->release($lockedReservation);

                $count++;
            }

            return $count;
        });
    }
}
