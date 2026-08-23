<?php

namespace App\Modules\Booking\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ExpireSeatHoldsAction
{
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
                $reservation->update(['status' => ReservationStatus::Expired]);

                $reservation->segments()
                    ->where('status', ReservationSegmentStatus::Held->value)
                    ->update([
                        'status' => ReservationSegmentStatus::Expired,
                        'released_at' => now(),
                    ]);

                $count++;
            }

            return $count;
        });
    }
}
