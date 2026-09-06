<?php

namespace App\Modules\SeatInventory\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use Illuminate\Support\Collection;

class FindOccupiedSeatIdsAction
{
    public function __construct(
        private DepartureStopTimeResolver $stopTimeResolver,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        $originOrder = $this->stopTimeResolver->circulationIndexOf($departure, $originStop);
        $destinationOrder = $this->stopTimeResolver->circulationIndexOf($departure, $destinationStop);

        return ReservationSegment::query()
            ->whereBelongsTo($departure)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->where('origin_stop_order', '<', $destinationOrder)
            ->where('destination_stop_order', '>', $originOrder)
            ->pluck('seat_id')
            ->unique()
            ->values();
    }
}
