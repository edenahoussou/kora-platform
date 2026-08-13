<?php

namespace App\Modules\SeatInventory\Actions;

use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Data\SeatAvailabilityData;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Illuminate\Support\Collection;

use function Illuminate\Support\enum_value;

class BuildSeatAvailabilityAction
{
    public function __construct(private FindOccupiedSeatIdsAction $findOccupiedSeatIds) {}

    /**
     * @return Collection<int, SeatAvailabilityData>
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        $departure->loadMissing(['bus.seats']);

        $occupiedSeatIds = $this->findOccupiedSeatIds->handle($departure, $originStop, $destinationStop);

        return $departure->bus->seats
            ->map(fn ($seat): SeatAvailabilityData => new SeatAvailabilityData(
                seat: $seat,
                availabilityStatus: $this->availabilityStatus($seat, $occupiedSeatIds),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, int>  $occupiedSeatIds
     */
    private function availabilityStatus(Seat $seat, Collection $occupiedSeatIds): SeatAvailabilityStatus
    {
        if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
            return SeatAvailabilityStatus::Blocked;
        }

        return $occupiedSeatIds->contains($seat->id)
            ? SeatAvailabilityStatus::Occupied
            : SeatAvailabilityStatus::Available;
    }
}
