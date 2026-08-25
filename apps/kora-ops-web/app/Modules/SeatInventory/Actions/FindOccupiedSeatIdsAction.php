<?php

namespace App\Modules\SeatInventory\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use Illuminate\Support\Collection;

class FindOccupiedSeatIdsAction
{
    /**
     * @return Collection<int, int>
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        return ReservationSegment::query()
            ->whereBelongsTo($departure)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', ReservationSegmentStatus::Held->value)
                    ->orWhereHas('reservation', function ($reservationQuery): void {
                        $reservationQuery
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->where('origin_stop_order', '<', $destinationStop->stop_order)
            ->where('destination_stop_order', '>', $originStop->stop_order)
            ->pluck('seat_id')
            ->unique()
            ->values();
    }
}
