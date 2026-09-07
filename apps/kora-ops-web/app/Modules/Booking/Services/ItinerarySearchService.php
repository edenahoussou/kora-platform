<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateFarePreviewAction;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        public readonly int $availableSeatsCount = 0,
        public readonly ?string $serviceClassCode = null,
        public readonly ?string $serviceClassName = null,
        public readonly ?int $estimatedDurationMinutes = null,
        public readonly ?Bus $bus = null,
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
    /** @var array<int, EloquentCollection<int, Stop>> */
    private array $stopsAtPhysicalCache = [];

    /** @var array<string, EloquentCollection<int, Departure>> */
    private array $departuresCache = [];

    /** @var array<int, EloquentCollection<int, ReservationSegment>> */
    private array $occupyingSegmentsCache = [];

    /** @var array<int, Line> line_id => Line with stops and fares.serviceClass loaded */
    private array $linesCache = [];

    /** @var array<int, Bus> bus_id => Bus with seats loaded */
    private array $busesCache = [];

    public function __construct(
        private DepartureStopTimeResolver $stopTimeResolver,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private CalculateFarePreviewAction $calculateFarePreview,
        private ResolveDepartureSellabilityAction $resolveDepartureSellability,
    ) {}

    /**
     * Reset per-search caches. Called at the start of each search() invocation
     * so that multi-day searches (searchUpcoming) and round-trip searches
     * (searchForCounterSale) do not accumulate stale data across calls.
     */
    private function resetSearchCache(): void
    {
        $this->stopsAtPhysicalCache = [];
        $this->departuresCache = [];
        $this->occupyingSegmentsCache = [];
        $this->linesCache = [];
        $this->busesCache = [];
    }

    /**
     * Counter-sale oriented search: resolves physical stops by city name and
     * returns both outbound and inbound itineraries in one call.
     *
     * @param  array{date?: string|null, from?: string|null, to?: string|null, trip_type?: string|null, return_date?: string|null, service_class?: string|null}  $filters
     * @return array{outbound: list<ItineraryData>, inbound: list<ItineraryData>}
     */
    public function searchForCounterSale(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($from === null || $to === null || $from === $to) {
            return ['outbound' => [], 'inbound' => []];
        }

        $this->resetSearchCache();

        $originPhysical = PhysicalStop::query()
            ->where('city', $from)
            ->where('status', 'active')
            ->first();

        $destinationPhysical = PhysicalStop::query()
            ->where('city', $to)
            ->where('status', 'active')
            ->first();

        if ($originPhysical === null || $destinationPhysical === null) {
            return ['outbound' => [], 'inbound' => []];
        }

        $inbound = [];
        $tripType = $filters['trip_type'] ?? 'one_way';
        $returnDate = $filters['return_date'] ?? null;

        $hasExplicitDate = isset($filters['date']) && $filters['date'] !== '';
        $outbound = $hasExplicitDate
            ? $this->searchWithPhysicalStops(
                originPhysical: $originPhysical,
                destinationPhysical: $destinationPhysical,
                date: CarbonImmutable::parse($filters['date']),
                passengerCount: 1,
            )
            : $this->searchUpcoming(
                originPhysicalStopUuid: $originPhysical->uuid,
                destinationPhysicalStopUuid: $destinationPhysical->uuid,
                fromDate: CarbonImmutable::today(),
                days: (int) config('kora.itineraries.default_search_days', 7),
                passengerCount: 1,
            );

        if ($tripType === 'round_trip') {
            if ($returnDate !== null) {
                $inbound = $this->searchWithPhysicalStops(
                    originPhysical: $destinationPhysical,
                    destinationPhysical: $originPhysical,
                    date: CarbonImmutable::parse($returnDate),
                    passengerCount: 1,
                );
            } else {
                // No return date — search upcoming return departures.
                // Start from the day after the outbound date (or today if
                // no outbound date) to avoid same-day returns on long trips.
                $fromDate = $hasExplicitDate
                    ? CarbonImmutable::parse($filters['date'])->addDay()
                    : CarbonImmutable::today();
                $inbound = $this->searchUpcoming(
                    originPhysicalStopUuid: $destinationPhysical->uuid,
                    destinationPhysicalStopUuid: $originPhysical->uuid,
                    fromDate: $fromDate,
                    days: (int) config('kora.itineraries.default_search_days', 7),
                    passengerCount: 1,
                );
            }
        }

        return ['outbound' => $outbound, 'inbound' => $inbound];
    }

    /**
     * Search across multiple days starting from $fromDate and merge results.
     *
     * Used when the counter-sale search has no explicit date — the agent sees
     * the next available departures instead of an empty list for today.
     * Also used by the mobile API for round-trip searches without a return date.
     *
     * @return list<ItineraryData>
     */
    public function searchUpcoming(
        string $originPhysicalStopUuid,
        string $destinationPhysicalStopUuid,
        CarbonImmutable $fromDate,
        int $days,
        int $passengerCount = 1,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        ?string $serviceClassCode = null,
    ): array {
        $days = max(1, min($days, 30));
        $merged = [];
        $seen = [];

        // Resolve physical stops once for all days instead of per-day.
        $originPhysical = PhysicalStop::query()->where('uuid', $originPhysicalStopUuid)->first();
        $destinationPhysical = PhysicalStop::query()->where('uuid', $destinationPhysicalStopUuid)->first();

        if (! $originPhysical || ! $destinationPhysical || $originPhysical->id === $destinationPhysical->id) {
            return [];
        }

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $fromDate->addDays($offset);
            $daily = $this->searchWithPhysicalStops(
                originPhysical: $originPhysical,
                destinationPhysical: $destinationPhysical,
                date: $date,
                passengerCount: $passengerCount,
                constrainFirstLegToDate: false,
            );

            foreach ($daily as $itinerary) {
                $key = $this->itineraryKey($itinerary);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $merged[] = $itinerary;
                }
            }

            if (count($merged) >= (int) config('kora.itineraries.max_results', 25)) {
                break;
            }
        }

        if ($serviceClassCode !== null) {
            $merged = $this->filterByServiceClass($merged, $serviceClassCode);
        }

        return $this->sortItinerariesBy($merged, $sortBy ?? 'connections', $sortDirection);
    }

    /**
     * @return list<ItineraryData>
     */
    public function search(
        string $originPhysicalStopUuid,
        string $destinationPhysicalStopUuid,
        CarbonImmutable $date,
        int $passengerCount = 1,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        ?string $serviceClassCode = null,
    ): array {
        if ($passengerCount < 1 || $passengerCount > 10) {
            return [];
        }

        $this->resetSearchCache();

        $originPhysical = PhysicalStop::query()->where('uuid', $originPhysicalStopUuid)->first();
        $destinationPhysical = PhysicalStop::query()->where('uuid', $destinationPhysicalStopUuid)->first();

        if (! $originPhysical || ! $destinationPhysical) {
            return [];
        }

        $itineraries = $this->searchWithPhysicalStops(
            originPhysical: $originPhysical,
            destinationPhysical: $destinationPhysical,
            date: $date,
            passengerCount: $passengerCount,
        );

        if ($serviceClassCode !== null) {
            $itineraries = $this->filterByServiceClass($itineraries, $serviceClassCode);
        }

        return $this->sortItinerariesBy($itineraries, $sortBy ?? 'connections', $sortDirection);
    }

    /**
     * Internal search that accepts already-resolved PhysicalStop models,
     * avoiding repeated UUID lookups across multi-day and round-trip searches.
     *
     * @return list<ItineraryData>
     */
    private function searchWithPhysicalStops(
        PhysicalStop $originPhysical,
        PhysicalStop $destinationPhysical,
        CarbonImmutable $date,
        int $passengerCount,
        bool $constrainFirstLegToDate = true,
    ): array {
        if ($passengerCount < 1 || $passengerCount > 10) {
            return [];
        }

        if ($originPhysical->getRawOriginal('status') !== 'active'
            || $destinationPhysical->getRawOriginal('status') !== 'active'
            || $originPhysical->id === $destinationPhysical->id
        ) {
            return [];
        }

        $maxLegs = (int) config('kora.itineraries.max_legs', 3);
        $marginMinutes = (int) config('kora.itineraries.connection_margin_minutes', 45);
        $maxWaitHours = (int) config('kora.itineraries.max_wait_hours', 12);

        $itineraries = $this->findItineraries(
            originPhysicalId: $originPhysical->id,
            destinationPhysicalId: $destinationPhysical->id,
            date: $date,
            maxLegs: $maxLegs,
            marginMinutes: $marginMinutes,
            maxWaitHours: $maxWaitHours,
            passengerCount: $passengerCount,
            constrainFirstLegToDate: $constrainFirstLegToDate,
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
        int $maxWaitHours,
        int $passengerCount,
        bool $constrainFirstLegToDate = true,
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
            maxWaitHours: $maxWaitHours,
            earliestNextDeparture: $date->startOfDay(),
            constrainFirstLegToDate: $constrainFirstLegToDate,
            passengerCount: $passengerCount,
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
        int $maxWaitHours,
        CarbonImmutable $earliestNextDeparture,
        bool $constrainFirstLegToDate,
        int $passengerCount,
        array &$results,
    ): void {
        if (count($legs) >= $maxLegs || count($results) >= (int) config('kora.itineraries.max_results', 25)) {
            return;
        }

        $legOrder = count($legs) + 1;

        // Find all stops at the current physical stop
        $currentStops = $this->getStopsAtPhysicalStop($currentPhysicalId);
        if ($currentStops->isEmpty()) {
            return;
        }

        // Find all departures from the current physical stop on this date
        // that depart after the earliest allowed time.
        // For the first leg with an explicit date, the departure's
        // departure_date must match the requested date. This correctly
        // includes overnight trips whose intermediate stops fall after
        // midnight UTC — the bus still belongs to the requested day. For
        // upcoming searches (no date specified), there is no upper bound so
        // overnight buses that depart after midnight are still found. For
        // connecting legs, the departure must be within maxWaitHours of the
        // previous leg's arrival.
        $latestDeparture = match (true) {
            $legs !== [] => $earliestNextDeparture->addHours($maxWaitHours),
            $constrainFirstLegToDate => null,
            default => null,
        };
        $maxDepartureDate = $legs === [] && $constrainFirstLegToDate
            ? $date
            : null;

        $departures = $this->getDeparturesFromStops($currentStops, $date, $earliestNextDeparture, $latestDeparture, $maxDepartureDate);

        // A connection means changing lines. Once we have at least one leg,
        // exclude departures on the same line as the previous leg — staying
        // on the same line is just a direct trip with an unnecessary stop,
        // not a real connection.
        $previousLineId = $legs === [] ? null : end($legs)->departure->line_id;

        // Track all physical stops already visited (including the origin)
        // to prevent the itinerary from looping back to a previous stop.
        // Without this, the search can produce nonsensical itineraries like
        // Cotonou → Allada → Cotonou → Dassa.
        $visitedPhysicalIds = [$currentPhysicalId];
        foreach ($legs as $existingLeg) {
            $visitedPhysicalIds[] = $existingLeg->originStop->physical_stop_id;
        }
        $visitedPhysicalIds = array_values(array_unique($visitedPhysicalIds));

        // Also track all lines already used in the itinerary. A connection
        // should not reuse a line from an earlier leg — that indicates the
        // itinerary is backtracking rather than making forward progress.
        $usedLineIds = [];
        foreach ($legs as $existingLeg) {
            $usedLineIds[] = $existingLeg->departure->line_id;
        }
        $usedLineIds = array_values(array_unique($usedLineIds));

        foreach ($departures as $departure) {
            if (in_array($departure->id, $usedDepartureIds, true)) {
                continue;
            }

            if ($previousLineId !== null && $departure->line_id === $previousLineId) {
                continue;
            }

            // Never reuse a line already used in an earlier leg. This
            // prevents backtracking itineraries like
            // Cotonou(L2)→Allada(L3)→Cotonou(L2)→Dassa.
            if (in_array($departure->line_id, $usedLineIds, true)) {
                continue;
            }

            // Skip departures that are no longer sellable (past cutoff)
            $sellability = $this->resolveDepartureSellability->handle($departure);
            if (! $sellability->isSellable) {
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

            // Build the actual circulation order for this departure.
            // When stop_times are present, they define the real order (which
            // may be reversed for return trips). Otherwise, fall back to the
            // line's canonical stop_order.
            $circulationOrder = $this->circulationOrder($departure, $stops);

            // Find the origin's position in the circulation order.
            $originIndex = array_search($originStop->id, array_keys($circulationOrder), true);
            if ($originIndex === false) {
                continue;
            }

            // Check each subsequent stop in the circulation order as a
            // potential destination for this leg.
            $orderedStops = array_slice($circulationOrder, $originIndex + 1, preserve_keys: true);

            $travelDate = CarbonImmutable::parse((string) $departure->departure_date);
            $fares = $departure->line->getRelation('fares');
            $occupyingSegments = $this->occupyingSegmentsForDeparture($departure);

            foreach ($orderedStops as $stop) {
                // Never go back to a physical stop already visited. This
                // prevents loops like Cotonou→Allada→Cotonou.
                if (in_array($stop->physical_stop_id, $visitedPhysicalIds, true)) {
                    continue;
                }

                $legArrivalAt = $this->getStopArrivalTime($departure, $stop);
                $legDepartureAt = $this->getStopDepartureTime($departure, $originStop);
                $legAmount = $this->getFareAmount($fares, $originStop, $stop, $travelDate);

                if ($legAmount === null || $legDepartureAt === null || $legArrivalAt === null) {
                    continue;
                }

                $availableSeatsCount = $this->buildSeatAvailability
                    ->countAvailableFromLoadedSegments($departure, $originStop, $stop, $occupyingSegments);

                if ($availableSeatsCount < $passengerCount) {
                    continue;
                }

                $farePreview = $this->calculateFarePreview->handleFromLoadedFares(
                    $fares,
                    $originStop,
                    $stop,
                    $travelDate,
                );

                $leg = new ItineraryLegData(
                    departure: $departure,
                    originStop: $originStop,
                    destinationStop: $stop,
                    legOrder: $legOrder,
                    amount: $legAmount,
                    departureAt: $legDepartureAt,
                    arrivalAt: $legArrivalAt,
                    availableSeatsCount: $availableSeatsCount,
                    serviceClassCode: $farePreview['service_class_code'] ?? null,
                    serviceClassName: $farePreview['service_class_name'] ?? null,
                    estimatedDurationMinutes: $departure->line->estimated_duration_minutes,
                    bus: $departure->bus,
                );

                if ($stop->physical_stop_id === $destinationPhysicalId) {
                    // Reached destination — complete itinerary
                    $allLegs = [...$legs, $leg];
                    $results[] = $this->buildItinerary($allLegs, $marginMinutes);
                } else {
                    // Intermediate stop — search for connecting legs
                    $nextEarliest = $legArrivalAt->addMinutes($marginMinutes);
                    // Max-wait constraint: the next departure must leave within
                    // maxWaitHours of the previous leg's arrival. This allows
                    // overnight connections (e.g. L1 Abidjan→Cotonou arriving
                    // at 00:30, then L2 Cotonou→Malanville at 06:00) without
                    // proposing absurd 20-hour layovers.
                    $latestDeparture = $legArrivalAt->addHours($maxWaitHours);
                    if ($nextEarliest > $latestDeparture) {
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
                        maxWaitHours: $maxWaitHours,
                        earliestNextDeparture: $nextEarliest,
                        constrainFirstLegToDate: $constrainFirstLegToDate,
                        passengerCount: $passengerCount,
                        results: $results,
                    );
                }
            }
        }
    }

    /**
     * Build the actual circulation order for a departure as an ordered
     * mapping of stop_id => Stop. Uses stop_times when available (which may
     * be reversed for return trips), otherwise falls back to the line's
     * canonical stop_order.
     *
     * @param  EloquentCollection<int, Stop>  $stops
     * @return array<int, Stop>
     */
    private function circulationOrder(Departure $departure, EloquentCollection $stops): array
    {
        $stopTimes = $departure->stop_times ?? [];

        if ($stopTimes === []) {
            return $stops->sortBy('stop_order')->keyBy('id')->all();
        }

        $stopsByUuid = $stops->keyBy('uuid');
        $ordered = [];

        foreach ($stopTimes as $stopTime) {
            $stopUuid = $stopTime['stop_uuid'] ?? null;
            if ($stopUuid === null) {
                continue;
            }

            $stop = $stopsByUuid->get($stopUuid);
            if ($stop !== null) {
                $ordered[$stop->id] = $stop;
            }
        }

        return $ordered !== [] ? $ordered : $stops->sortBy('stop_order')->keyBy('id')->all();
    }

    /**
     * @return EloquentCollection<int, Stop>
     */
    private function getStopsAtPhysicalStop(int $physicalStopId): EloquentCollection
    {
        return $this->stopsAtPhysicalCache[$physicalStopId] ??= Stop::query()
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
        ?CarbonImmutable $latestDeparture = null,
        ?CarbonImmutable $maxDepartureDate = null,
    ): EloquentCollection {
        $lineIds = $stops->pluck('line_id')->unique()->values()->all();

        if ($lineIds === []) {
            return new EloquentCollection;
        }

        // The DB query result is the same for the same (lineIds, date) pair.
        // The earliestDeparture filter is applied in PHP below, so we can
        // cache the raw query result and re-filter per recursive call.
        // We query two days (date and date+1) to allow overnight connections
        // where the first leg arrives after midnight and the connecting leg
        // departs the next morning.
        $cacheKey = md5(implode(',', $lineIds).'|'.$date->toDateString());

        if (! isset($this->departuresCache[$cacheKey])) {
            // Query only the departures table — lines, buses, and their
            // relations are loaded once via preloadNetworkData and attached
            // manually, avoiding repeated eager-load queries across days.
            $departures = Departure::query()
                ->whereDate('departure_date', $date)
                ->where('status', DepartureStatus::Scheduled->value)
                ->whereIn('line_id', $lineIds)
                ->get();

            // Also load departures for the next day to support overnight
            // connections within the max-wait window.
            $nextDay = $date->addDay();
            $nextDayDepartures = Departure::query()
                ->whereDate('departure_date', $nextDay)
                ->where('status', DepartureStatus::Scheduled->value)
                ->whereIn('line_id', $lineIds)
                ->get();

            $departures = $departures->merge($nextDayDepartures);

            $this->preloadNetworkData($departures);

            foreach ($departures as $departure) {
                $departure->setRelation('line', $this->linesCache[$departure->line_id] ?? null);
                $departure->setRelation('bus', $this->busesCache[$departure->bus_id] ?? null);
            }

            $this->departuresCache[$cacheKey] = $departures;
        }

        return $this->departuresCache[$cacheKey]
            ->filter(function (Departure $departure) use ($stops, $earliestDeparture, $latestDeparture, $maxDepartureDate): bool {
                // When constraining by departure date (first leg with explicit
                // date), accept any departure whose departure_date falls within
                // the requested date. This correctly includes overnight trips
                // whose intermediate stops fall after midnight UTC — the bus
                // still belongs to the requested day.
                if ($maxDepartureDate !== null) {
                    $depDate = CarbonImmutable::parse((string) $departure->departure_date);
                    if ($depDate->greaterThan($maxDepartureDate)) {
                        return false;
                    }
                }

                return $stops
                    ->where('line_id', $departure->line_id)
                    ->contains(function (Stop $stop) use ($departure, $earliestDeparture, $latestDeparture): bool {
                        $stopDepartureAt = $this->getStopDepartureTime($departure, $stop);

                        if ($stopDepartureAt === null || $stopDepartureAt->lessThan($earliestDeparture)) {
                            return false;
                        }

                        if ($latestDeparture !== null && $stopDepartureAt->greaterThan($latestDeparture)) {
                            return false;
                        }

                        return true;
                    });
            })
            ->values();
    }

    /**
     * Pre-load lines (with stops and fares.serviceClass) and buses (with
     * seats) referenced by the given departures, caching them by ID so that
     * departures on different dates share the same relation data.
     *
     * @param  EloquentCollection<int, Departure>  $departures
     */
    private function preloadNetworkData(EloquentCollection $departures): void
    {
        $missingLineIds = $departures->pluck('line_id')
            ->unique()
            ->filter(fn ($id): bool => ! isset($this->linesCache[$id]))
            ->values()
            ->all();

        if ($missingLineIds !== []) {
            $lines = Line::query()
                ->whereIn('id', $missingLineIds)
                ->with([
                    'stops' => fn ($q) => $q->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                    'fares' => fn ($q) => $q->where('status', 'active')->with('serviceClass'),
                ])
                ->get();

            foreach ($lines as $line) {
                $this->linesCache[$line->id] = $line;
            }
        }

        $missingBusIds = $departures->pluck('bus_id')
            ->unique()
            ->filter(fn ($id): bool => $id !== null && ! isset($this->busesCache[$id]))
            ->values()
            ->all();

        if ($missingBusIds !== []) {
            $buses = Bus::query()
                ->whereIn('id', $missingBusIds)
                ->with(['seats', 'amenities'])
                ->get();

            foreach ($buses as $bus) {
                $this->busesCache[$bus->id] = $bus;
            }
        }
    }

    private function getStopDepartureTime(Departure $departure, Stop $stop): ?CarbonImmutable
    {
        return $this->stopTimeResolver->departureAt($departure, $stop);
    }

    private function getStopArrivalTime(Departure $departure, Stop $stop): ?CarbonImmutable
    {
        return $this->stopTimeResolver->arrivalAt($departure, $stop);
    }

    /**
     * Resolve the fare amount from the pre-loaded fares collection on the
     * departure's line, avoiding per-leg database queries.
     *
     * @param  EloquentCollection<int, Fare>  $fares
     */
    private function getFareAmount(EloquentCollection $fares, Stop $origin, Stop $destination, CarbonInterface $travelDate): ?int
    {
        // Direct fare: origin → destination
        $fare = $fares
            ->where('origin_stop_id', $origin->id)
            ->where('destination_stop_id', $destination->id)
            ->filter(fn (Fare $f): bool => $this->fareEffectiveOn($f, $travelDate))
            ->sortByDesc('effective_from')
            ->first();

        if ($fare) {
            return $fare->base_amount;
        }

        // Fallback: reverse fare (destination → origin)
        $reverseFare = $fares
            ->where('origin_stop_id', $destination->id)
            ->where('destination_stop_id', $origin->id)
            ->filter(fn (Fare $f): bool => $this->fareEffectiveOn($f, $travelDate))
            ->sortByDesc('effective_from')
            ->first();

        return $reverseFare?->base_amount;
    }

    /**
     * Check whether a fare is effective on the given travel date.
     */
    private function fareEffectiveOn(Fare $fare, CarbonInterface $travelDate): bool
    {
        $from = $fare->effective_from;
        $until = $fare->effective_until;

        if ($from !== null && $from->toDateString() > $travelDate->toDateString()) {
            return false;
        }

        if ($until !== null && $until->toDateString() < $travelDate->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * Load all occupying reservation segments for a departure, cached per
     * departure within a single search so that multiple (origin, destination)
     * pairs on the same departure share one database query.
     *
     * @return EloquentCollection<int, ReservationSegment>
     */
    private function occupyingSegmentsForDeparture(Departure $departure): EloquentCollection
    {
        return $this->occupyingSegmentsCache[$departure->id] ??= $this->buildSeatAvailability
            ->loadAllOccupyingSegments($departure);
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
     * Build a unique key for an itinerary, used to deduplicate results
     * across multiple days in searchUpcoming where the same departure can
     * appear in more than one day's search window.
     */
    private function itineraryKey(ItineraryData $itinerary): string
    {
        return collect($itinerary->legs)
            ->map(fn (ItineraryLegData $leg): string => "{$leg->departure->uuid}:{$leg->originStop->uuid}:{$leg->destinationStop->uuid}")
            ->implode('|');
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

    /**
     * @param  list<ItineraryData>  $itineraries
     * @return list<ItineraryData>
     */
    private function filterByServiceClass(array $itineraries, string $serviceClassCode): array
    {
        return array_values(array_filter(
            $itineraries,
            fn (ItineraryData $itinerary): bool => array_any(
                $itinerary->legs,
                fn (ItineraryLegData $leg): bool => $leg->serviceClassCode === $serviceClassCode,
            ),
        ));
    }

    /**
     * @param  list<ItineraryData>  $itineraries
     * @return list<ItineraryData>
     */
    private function sortItinerariesBy(array $itineraries, string $sortBy, string $sortDirection): array
    {
        $multiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($itineraries, function (ItineraryData $a, ItineraryData $b) use ($sortBy, $multiplier): int {
            $comparison = match ($sortBy) {
                'departure_time' => $a->departureAt <=> $b->departureAt,
                'arrival_time' => $a->arrivalAt <=> $b->arrivalAt,
                'price' => $a->totalAmount <=> $b->totalAmount,
                'duration' => $a->arrivalAt->diffInMinutes($a->departureAt) <=> $b->arrivalAt->diffInMinutes($b->departureAt),
                'connections' => $a->connectionsCount <=> $b->connectionsCount,
                default => $a->connectionsCount <=> $b->connectionsCount,
            };

            if ($comparison === 0) {
                // Stable secondary sort: direct first, then by departure time
                if ($a->connectionsCount !== $b->connectionsCount) {
                    return $a->connectionsCount <=> $b->connectionsCount;
                }

                return $a->departureAt <=> $b->departureAt;
            }

            return $comparison * $multiplier;
        });

        return $itineraries;
    }
}
