<?php

namespace App\Modules\SeatInventory\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Data\CounterSaleSeatAvailabilityData;
use App\Modules\SeatInventory\Data\CounterSaleSeatStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

use function Illuminate\Support\enum_value;

class BuildCounterSaleSeatAvailabilityAction
{
    /**
     * @return Collection<int, CounterSaleSeatAvailabilityData>
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): Collection
    {
        $departure->loadMissing(['bus.seats']);

        $segments = ReservationSegment::query()
            ->whereBelongsTo($departure)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->where('origin_stop_order', '<', $destinationStop->stop_order)
            ->where('destination_stop_order', '>', $originStop->stop_order)
            ->with(['reservation:id,expires_at'])
            ->get(['id', 'reservation_id', 'seat_id', 'status'])
            ->keyBy('seat_id');

        return $departure->bus->seats
            ->map(function (Seat $seat) use ($segments): CounterSaleSeatAvailabilityData {
                /** @var ReservationSegment|null $segment */
                $segment = $segments->get($seat->id);
                $status = $this->availabilityStatus($seat, $segment);

                return new CounterSaleSeatAvailabilityData(
                    seat: $seat,
                    availabilityStatus: $status,
                    expiresAt: $status === CounterSaleSeatStatus::Reserved
                        ? $this->expiration($segment)
                        : null,
                );
            })
            ->values();
    }

    private function availabilityStatus(Seat $seat, ?ReservationSegment $segment): CounterSaleSeatStatus
    {
        if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
            return CounterSaleSeatStatus::Blocked;
        }

        if ($segment === null) {
            return CounterSaleSeatStatus::Available;
        }

        return match ($segment->status) {
            ReservationSegmentStatus::Held, ReservationSegmentStatus::Confirmed => CounterSaleSeatStatus::Reserved,
            ReservationSegmentStatus::Paid,
            ReservationSegmentStatus::Ticketed,
            ReservationSegmentStatus::Boarded => CounterSaleSeatStatus::Paid,
            default => CounterSaleSeatStatus::Available,
        };
    }

    private function expiration(?ReservationSegment $segment): ?CarbonImmutable
    {
        $expiresAt = $segment?->reservation?->expires_at;

        return $expiresAt === null ? null : CarbonImmutable::parse((string) $expiresAt);
    }
}
