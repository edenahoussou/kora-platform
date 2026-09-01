<?php

namespace App\Modules\Booking\Services;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Itinerary data for a single leg of a multi-line journey.
 */
final class ItineraryLegData
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly int $legOrder,
        public readonly int $amount,
        public readonly CarbonImmutable $departureAt,
        public readonly CarbonImmutable $arrivalAt,
    ) {}
}

/**
 * Itinerary data for a complete multi-line journey (one or more legs).
 */
final class ItineraryData
{
    /**
     * @param  list<ItineraryLegData>  $legs
     */
    public function __construct(
        public readonly array $legs,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly int $connectionsCount,
        public readonly CarbonImmutable $departureAt,
        public readonly CarbonImmutable $arrivalAt,
        public readonly int $connectionMarginMinutes,
    ) {}

    public function isDirect(): bool
    {
        return $this->connectionsCount === 0;
    }
}

/**
 * Searches for multi-line itineraries with connections.
 *
 * Given an origin and destination physical stop and a date, finds all valid
 * same-day itineraries (direct and with connections) sorted by:
 * 1. Number of connections (ascending)
 * 2. Final arrival time (ascending)
 * 3. Total price (ascending)
 */
class ItinerarySearchService
{
    /**
     * @return list<ItineraryData>
     */
    public function search(
        string $originPhysicalStopUuid,
        string $destinationPhysicalStopUuid,
        CarbonImmutable $date,
    ): array {
        $originPhysical = PhysicalStop::query()->where('uuid', $originPhysicalStopUuid)->first();
        $destinationPhysical = PhysicalStop::query()->where('uuid', $destinationPhysicalStopUuid)->first();

        if (! $originPhysical || ! $destinationPhysical || $originPhysical->id === $destinationPhysical->id) {
            return [];
        }

        $maxLegs = (int) config('kora.itineraries.max_legs', 3);
        $marginMinutes = (int) config('kora.itineraries.connection_margin_minutes', 45);

        $itineraries = $this->findItineraries(
            originPhysicalId: $originPhysical->id,
            destinationPhysicalId: $destinationPhysical->id,
            date: $date,
            maxLegs: $maxLegs,
            marginMinutes: $marginMinutes,
        );

        return $this->sortItineraries($itineraries);
    }

    /**
     * @return list<ItineraryData>
     */
    private function findItineraries(
        int $originPhysicalId,
        int $destinationPhysicalId,
        CarbonImmutable $date,
        int $maxLegs,
        int $marginMinutes,
    ): array {
        $itineraries = [];
        $this->searchRecursive(
            currentPhysicalId: $originPhysicalId,
            destinationPhysicalId: $destinationPhysicalId,
            date: $date,
            legs: [],
            usedDepartureIds: [],
            maxLegs: $maxLegs,
            marginMinutes: $marginMinutes,
            earliestNextDeparture: $date->startOfDay(),
            results: $itineraries,
        );

        return $itineraries;
    }

    /**
     * Recursive BFS search for itineraries.
     *
     * @param  list<ItineraryLegData>  $legs
     * @param  array<int, int>  $usedDepartureIds
     * @param  list<ItineraryData>  $results
     */
    private function searchRecursive(
        int $currentPhysicalId,
        int $destinationPhysicalId,
        CarbonImmutable $date,
        array $legs,
        array $usedDepartureIds,
        int $maxLegs,
        int $marginMinutes,
        CarbonImmutable $earliestNextDeparture,
        array &$results,
    ): void {
        if (count($legs) >= $maxLegs) {
            return;
        }

        $legOrder = count($legs) + 1;

        // Find all stops at the current physical stop
        $currentStops = $this->getStopsAtPhysicalStop($currentPhysicalId);
        if ($currentStops->isEmpty()) {
            return;
        }

        // Find all departures from the current physical stop on this date
        // that depart after the earliest allowed time
        $departures = $this->getDeparturesFromStops($currentStops, $date, $earliestNextDeparture);

        foreach ($departures as $departure) {
            if (in_array($departure->id, $usedDepartureIds, true)) {
                continue;
            }

            $line = $departure->line;
            $stops = $line->getRelation('stops');
            if (! $stops instanceof EloquentCollection) {
                continue;
            }

            /** @var EloquentCollection<int, Stop> $stops */

            // Find the origin stop on this line (matching current physical stop)
            /** @var Stop|null $originStop */
            $originStop = $stops->first(fn (Stop $s): bool => $s->physical_stop_id === $currentPhysicalId);
            if (! $originStop) {
                continue;
            }

            // Check each subsequent stop as a potential destination for this leg
            foreach ($stops as $stop) {
                if ($stop->stop_order <= $originStop->stop_order) {
                    continue;
                }

                $legArrivalAt = $this->getStopArrivalTime($departure, $stop);
                $legDepartureAt = $this->getStopDepartureTime($departure, $originStop);
                $legAmount = $this->getFareAmount($line, $originStop, $stop);

                if ($legAmount === null) {
                    continue;
                }

                $leg = new ItineraryLegData(
                    departure: $departure,
                    originStop: $originStop,
                    destinationStop: $stop,
                    legOrder: $legOrder,
                    amount: $legAmount,
                    departureAt: $legDepartureAt,
                    arrivalAt: $legArrivalAt,
                );

                if ($stop->physical_stop_id === $destinationPhysicalId) {
                    // Reached destination — complete itinerary
                    $allLegs = [...$legs, $leg];
                    $results[] = $this->buildItinerary($allLegs, $marginMinutes);
                } else {
                    // Intermediate stop — search for connecting legs
                    $nextEarliest = $legArrivalAt->addMinutes($marginMinutes);
                    // Same-day constraint: next departure must be on the same calendar day
                    if (! $nextEarliest->isSameDay($date)) {
                        continue;
                    }

                    $this->searchRecursive(
                        currentPhysicalId: $stop->physical_stop_id,
                        destinationPhysicalId: $destinationPhysicalId,
                        date: $date,
                        legs: [...$legs, $leg],
                        usedDepartureIds: [...$usedDepartureIds, $departure->id],
                        maxLegs: $maxLegs,
                        marginMinutes: $marginMinutes,
                        earliestNextDeparture: $nextEarliest,
                        results: $results,
                    );
                }
            }
        }
    }

    /**
     * @return EloquentCollection<int, Stop>
     */
    private function getStopsAtPhysicalStop(int $physicalStopId): EloquentCollection
    {
        return Stop::query()
            ->where('physical_stop_id', $physicalStopId)
            ->where('status', StopStatus::Active->value)
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Stop>  $stops
     * @return EloquentCollection<int, Departure>
     */
    private function getDeparturesFromStops(
        EloquentCollection $stops,
        CarbonImmutable $date,
        CarbonImmutable $earliestDeparture,
    ): EloquentCollection {
        $lineIds = $stops->pluck('line_id')->unique()->values()->all();

        if ($lineIds === []) {
            return new EloquentCollection;
        }

        return Departure::query()
            ->whereDate('departure_date', $date)
            ->where('status', DepartureStatus::Scheduled->value)
            ->whereIn('line_id', $lineIds)
            ->with(['line.stops' => fn ($q) => $q->where('status', StopStatus::Active->value)->orderBy('stop_order')])
            ->get()
            ->filter(function (Departure $departure) use ($earliestDeparture): bool {
                $scheduledAt = $departure->scheduledDepartureAt();

                return $scheduledAt->greaterThanOrEqualTo($earliestDeparture);
            })
            ->values();
    }

    private function getStopDepartureTime(Departure $departure, Stop $stop): CarbonImmutable
    {
        $stopTimes = $departure->stop_times;
        if (is_array($stopTimes)) {
            foreach ($stopTimes as $st) {
                if (($st['stop_id'] ?? null) === $stop->id && isset($st['departure_time'])) {
                    return CarbonImmutable::parse($st['departure_time']);
                }
            }
        }

        // Fallback: if this is the first stop, use the departure's scheduled time
        if ($stop->stop_order === 1) {
            return $departure->scheduledDepartureAt();
        }

        // Fallback: use the departure's scheduled time (approximate)
        return $departure->scheduledDepartureAt();
    }

    private function getStopArrivalTime(Departure $departure, Stop $stop): CarbonImmutable
    {
        $stopTimes = $departure->stop_times;
        if (is_array($stopTimes)) {
            foreach ($stopTimes as $st) {
                if (($st['stop_id'] ?? null) === $stop->id && isset($st['arrival_time'])) {
                    return CarbonImmutable::parse($st['arrival_time']);
                }
            }
        }

        // Fallback: if this is the last stop, use estimated_arrival_at
        $stops = $departure->line->getRelation('stops');
        if ($stops instanceof EloquentCollection) {
            /** @var Stop|null $lastStop */
            $lastStop = $stops->last();
            if ($lastStop && $stop->id === $lastStop->id) {
                return $departure->estimated_arrival_at
                    ? CarbonImmutable::instance($departure->estimated_arrival_at)
                    : $departure->scheduledDepartureAt();
            }
        }

        // Fallback: use estimated_arrival_at (approximate)
        return $departure->estimated_arrival_at
            ? CarbonImmutable::instance($departure->estimated_arrival_at)
            : $departure->scheduledDepartureAt();
    }

    private function getFareAmount(Line $line, Stop $origin, Stop $destination): ?int
    {
        $fare = $line->fares()
            ->where('origin_stop_id', $origin->id)
            ->where('destination_stop_id', $destination->id)
            ->where('status', 'active')
            ->first();

        return $fare?->base_amount;
    }

    /**
     * @param  list<ItineraryLegData>  $legs
     */
    private function buildItinerary(array $legs, int $marginMinutes): ItineraryData
    {
        $totalAmount = array_sum(array_map(fn (ItineraryLegData $leg): int => $leg->amount, $legs));
        $firstLeg = $legs[0];
        $lastLeg = $legs[count($legs) - 1];

        return new ItineraryData(
            legs: $legs,
            totalAmount: $totalAmount,
            currency: 'XOF',
            connectionsCount: count($legs) - 1,
            departureAt: $firstLeg->departureAt,
            arrivalAt: $lastLeg->arrivalAt,
            connectionMarginMinutes: $marginMinutes,
        );
    }

    /**
     * @param  list<ItineraryData>  $itineraries
     * @return list<ItineraryData>
     */
    private function sortItineraries(array $itineraries): array
    {
        usort($itineraries, function (ItineraryData $a, ItineraryData $b): int {
            if ($a->connectionsCount !== $b->connectionsCount) {
                return $a->connectionsCount <=> $b->connectionsCount;
            }
            if (! $a->arrivalAt->equalTo($b->arrivalAt)) {
                return $a->arrivalAt <=> $b->arrivalAt;
            }

            return $a->totalAmount <=> $b->totalAmount;
        });

        return $itineraries;
    }
}
