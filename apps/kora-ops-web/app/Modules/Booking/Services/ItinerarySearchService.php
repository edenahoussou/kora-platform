<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Actions\FindOrCreatePhysicalStopAction;
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
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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

        // A city can host several physical stops (gare + named arrêts).
        // Resolve all of them: picking first() would silently miss
        // departures served by a different stop in the same city.
        $originPhysicals = PhysicalStop::query()
            ->where('city', $from)
            ->where('status', 'active')
            ->get();

        $destinationPhysicals = PhysicalStop::query()
            ->where('city', $to)
            ->where('status', 'active')
            ->get();

        if ($originPhysicals->isEmpty() || $destinationPhysicals->isEmpty()) {
            return ['outbound' => [], 'inbound' => []];
        }

        $inbound = [];
        $tripType = $filters['trip_type'] ?? 'one_way';
        $returnDate = $filters['return_date'] ?? null;
        $serviceClassCode = $filters['service_class'] ?? null;
        $defaultSearchDays = app(OperationsSettings::class)->defaultSearchDays;

        $hasExplicitDate = isset($filters['date']) && $filters['date'] !== '';
        $outbound = $this->searchAcrossCityStops(
            originPhysicals: $originPhysicals,
            destinationPhysicals: $destinationPhysicals,
            date: $hasExplicitDate ? CarbonImmutable::parse($filters['date']) : null,
            fromDate: CarbonImmutable::today(),
            days: $defaultSearchDays,
        );

        if ($tripType === 'round_trip') {
            $inboundFromDate = $hasExplicitDate && $returnDate === null
                ? CarbonImmutable::parse($filters['date'])->addDay()
                : CarbonImmutable::today();
            $inbound = $this->searchAcrossCityStops(
                originPhysicals: $destinationPhysicals,
                destinationPhysicals: $originPhysicals,
                date: $returnDate !== null ? CarbonImmutable::parse($returnDate) : null,
                fromDate: $inboundFromDate,
                days: $defaultSearchDays,
            );
        }

        if ($serviceClassCode !== null) {
            $outbound = $this->filterByServiceClass($outbound, $serviceClassCode);
            $inbound = $this->filterByServiceClass($inbound, $serviceClassCode);
        }

        return ['outbound' => $outbound, 'inbound' => $inbound];
    }

    /**
     * Run the search for every (origin, destination) physical-stop pair in the
     * two cities and merge deduplicated results. A city can be served by
     * several physical stops — searching only first() silently drops
     * departures boarding elsewhere in the same city.
     *
     * @param  EloquentCollection<int, PhysicalStop>  $originPhysicals
     * @param  EloquentCollection<int, PhysicalStop>  $destinationPhysicals
     * @return list<ItineraryData>
     */
    private function searchAcrossCityStops(
        EloquentCollection $originPhysicals,
        EloquentCollection $destinationPhysicals,
        ?CarbonImmutable $date,
        CarbonImmutable $fromDate,
        int $days,
    ): array {
        $merged = [];
        $seen = [];

        foreach ($originPhysicals as $originPhysical) {
            foreach ($destinationPhysicals as $destinationPhysical) {
                if ($originPhysical->id === $destinationPhysical->id) {
                    continue;
                }

                $daily = $date !== null
                    ? $this->searchWithPhysicalStops(
                        originPhysical: $originPhysical,
                        destinationPhysical: $destinationPhysical,
                        date: $date,
                        passengerCount: 1,
                    )
                    : $this->searchUpcoming(
                        originPhysicalStopUuid: $originPhysical->uuid,
                        destinationPhysicalStopUuid: $destinationPhysical->uuid,
                        fromDate: $fromDate,
                        days: $days,
                        passengerCount: 1,
                    );

                foreach ($daily as $itinerary) {
                    $key = $this->itineraryKey($itinerary);
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $merged[] = $itinerary;
                    }
                }
            }
        }

        $merged = $this->sortItineraries($merged);

        return array_slice($merged, 0, app(OperationsSettings::class)->maxResults);
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

            if (count($merged) >= app(OperationsSettings::class)->maxResults) {
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
     * Active physical stops of a city, compared case- and accent-insensitively.
     *
     * @return list<string>
     */
    public function physicalStopUuidsForCity(string $city): array
    {
        $normalized = FindOrCreatePhysicalStopAction::normalize($city);

        $uuids = [];

        /** @var PhysicalStop $stop */
        foreach (PhysicalStop::query()->where('status', 'active')->whereNotNull('city')->get(['uuid', 'city']) as $stop) {
            if (FindOrCreatePhysicalStopAction::normalize((string) $stop->city) === $normalized) {
                $uuids[] = (string) $stop->uuid;
            }
        }

        return $uuids;
    }

    /**
     * Runs the search for every origin/destination stop pair and merges deduplicated results.
     *
     * @param  list<string>  $originUuids
     * @param  list<string>  $destinationUuids
     * @return list<ItineraryData>
     */
    public function searchBetweenStops(
        array $originUuids,
        array $destinationUuids,
        CarbonImmutable $date,
        int $passengerCount = 1,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        ?string $serviceClassCode = null,
        ?int $upcomingDays = null,
    ): array {
        $merged = [];

        foreach ($originUuids as $originUuid) {
            foreach ($destinationUuids as $destinationUuid) {
                if ($originUuid === $destinationUuid) {
                    continue;
                }

                $results = $upcomingDays !== null
                    ? $this->searchUpcoming($originUuid, $destinationUuid, $date, $upcomingDays, $passengerCount, $sortBy, $sortDirection, $serviceClassCode)
                    : $this->search($originUuid, $destinationUuid, $date, $passengerCount, $sortBy, $sortDirection, $serviceClassCode);

                foreach ($results as $itinerary) {
                    $merged[$this->itineraryKey($itinerary)] ??= $itinerary;
                }
            }
        }

        return $this->sortItinerariesBy(array_values($merged), $sortBy ?? 'connections', $sortDirection);
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

        $settings = app(OperationsSettings::class);
        $maxLegs = $settings->maxLegs;
        $marginMinutes = $settings->connectionMarginMinutes;
        $maxWaitHours = $settings->maxWaitHours;

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
        if (count($legs) >= $maxLegs || count($results) >= app(OperationsSettings::class)->maxResults) {
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

                // Apply dynamic pricing per leg so search results show the
                // actual price the customer will pay, not just the static base
                // fare. Falls back to the static base amount when no fare or
                // no pricing engine result is available.
                $farePreview = $this->calculateFarePreview->handleForDeparture(
                    $departure,
                    $originStop,
                    $stop,
                );

                if ($farePreview !== null) {
                    $baseAmount = (int) $farePreview['base_amount'];
                    $coefficient = (float) $farePreview['coefficient'];
                    $finalAmount = (int) $farePreview['final_amount'];
                    $legAmount = $finalAmount;
                } else {
                    $baseAmount = $legAmount;
                    $coefficient = 1.0;
                    $finalAmount = $legAmount;
                }

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
                    baseAmount: $baseAmount,
                    coefficient: $coefficient,
                    finalAmount: $finalAmount,
                    dynamicAdjustmentAmount: $finalAmount - $baseAmount,
                    pricingRuleUuid: $farePreview['pricing_rule_uuid'] ?? null,
                    fillRate: $farePreview['fill_rate'] ?? null,
                    hoursBeforeDeparture: $farePreview['hours_before_departure'] ?? null,
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
        // Load window = configured search days around the date: covers
        // multi-day trips still on the road and connecting legs on date+2+.
        $cacheKey = md5(implode(',', $lineIds).'|'.$date->toDateString());

        if (! isset($this->departuresCache[$cacheKey])) {
            $windowDays = max(1, min(30, app(OperationsSettings::class)->defaultSearchDays));

            // Query only the departures table — lines, buses, and their
            // relations are loaded once via preloadNetworkData and attached
            // manually, avoiding repeated eager-load queries across days.
            $departures = Departure::query()
                ->whereDate('departure_date', '>=', $date->subDays($windowDays))
                ->whereDate('departure_date', '<=', $date->addDays($windowDays))
                ->where('status', DepartureStatus::Scheduled->value)
                ->whereIn('line_id', $lineIds)
                ->get();

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
