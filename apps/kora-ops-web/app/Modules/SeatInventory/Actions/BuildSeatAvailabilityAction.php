<?php

namespace App\Modules\SeatInventory\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Data\SeatAvailabilityData;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

use function Illuminate\Support\enum_value;

class BuildSeatAvailabilityAction
{
    /**
     * @return Collection<int, SeatAvailabilityData>
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        $departure->loadMissing(['bus.seats']);

        $segments = $this->loadOverlappingSegments($departure, $originStop, $destinationStop);

        return $departure->bus->seats
            ->map(function (Seat $seat) use ($segments): SeatAvailabilityData {
                /** @var ReservationSegment|null $segment */
                $segment = $segments->get($seat->id);
                $status = $this->availabilityStatus($seat, $segment);

                return new SeatAvailabilityData(
                    seat: $seat,
                    availabilityStatus: $status,
                    heldUntil: $status === SeatAvailabilityStatus::Held
                        ? $this->expiration($segment)
                        : null,
                );
            })
            ->values();
    }

    /**
     * Count available seats using a pre-loaded collection of ALL occupying
     * segments for the departure (without stop-order filtering). The
     * stop-order overlap is applied in PHP, avoiding a database query per
     * (origin, destination) pair.
     *
     * The departure must already have bus.seats loaded.
     *
     * @param  EloquentCollection<int, ReservationSegment>  $allOccupyingSegments
     */
    public function countAvailableFromLoadedSegments(
        Departure $departure,
        Stop $originStop,
        Stop $destinationStop,
        EloquentCollection $allOccupyingSegments,
    ): int {
        $overlapping = $allOccupyingSegments
            ->where('origin_stop_order', '<', $destinationStop->stop_order)
            ->where('destination_stop_order', '>', $originStop->stop_order)
            ->keyBy('seat_id');

        return $departure->bus->seats
            ->filter(function (Seat $seat) use ($overlapping): bool {
                if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
                    return false;
                }

                /** @var ReservationSegment|null $segment */
                $segment = $overlapping->get($seat->id);

                return $segment === null
                    || ! in_array($segment->status, ReservationSegmentStatus::occupyingCases(), true);
            })
            ->count();
    }

    /**
     * Load all occupying reservation segments for a departure, without
     * stop-order filtering. The caller filters by stop-order in PHP.
     *
     * @return EloquentCollection<int, ReservationSegment>
     */
    public function loadAllOccupyingSegments(Departure $departure): EloquentCollection
    {
        return ReservationSegment::query()
            ->whereBelongsTo($departure)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->with(['reservation:id,expires_at'])
            ->get(['id', 'reservation_id', 'seat_id', 'status', 'origin_stop_order', 'destination_stop_order']);
    }

    /**
     * @return Collection<int, ReservationSegment>
     */
    private function loadOverlappingSegments(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        return ReservationSegment::query()
            ->whereBelongsTo($departure)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->where('origin_stop_order', '<', $destinationStop->stop_order)
            ->where('destination_stop_order', '>', $originStop->stop_order)
            ->with(['reservation:id,expires_at'])
            ->get(['id', 'reservation_id', 'seat_id', 'status'])
            ->keyBy('seat_id');
    }

    private function availabilityStatus(Seat $seat, ?ReservationSegment $segment): SeatAvailabilityStatus
    {
        if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
            return SeatAvailabilityStatus::Blocked;
        }

        if ($segment === null) {
            return SeatAvailabilityStatus::Available;
        }

        return match ($segment->status) {
            ReservationSegmentStatus::Held => SeatAvailabilityStatus::Held,
            ReservationSegmentStatus::Confirmed,
            ReservationSegmentStatus::Paid,
            ReservationSegmentStatus::Ticketed,
            ReservationSegmentStatus::Boarded => SeatAvailabilityStatus::Occupied,
            default => SeatAvailabilityStatus::Available,
        };
    }

    private function expiration(?ReservationSegment $segment): ?CarbonImmutable
    {
        $expiresAt = $segment?->reservation?->expires_at;

        return $expiresAt === null ? null : CarbonImmutable::parse((string) $expiresAt);
    }
}
