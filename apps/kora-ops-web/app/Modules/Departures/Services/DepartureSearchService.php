<?php

namespace App\Modules\Departures\Services;

use App\Modules\BusNetwork\Actions\ResolveLineSegmentStopsAction;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Data\DepartureAvailabilityData;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateFarePreviewAction;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\SeatAvailabilityData;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DepartureSearchService
{
    public function __construct(
        private ResolveLineSegmentStopsAction $resolveLineSegmentStops,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private CalculateFarePreviewAction $calculateFarePreview,
        private ResolveDepartureSellabilityAction $resolveDepartureSellability,
    ) {}

    /**
     * List departures for the public search API, paginated at the database level.
     *
     * Used by the passenger app. Only the current page of departures is loaded
     * and has its (expensive) seat availability / fare preview computed.
     *
     * @param  array{date?: string|null, line?: string|null, from?: string|null, to?: string|null, per_page?: int, service_class?: string|null}  $filters
     * @return LengthAwarePaginator<int, DepartureAvailabilityData>
     */
    public function search(array $filters, bool $sellableOnly = false): LengthAwarePaginator
    {
        $date = $this->parseDateFilter($filters['date'] ?? null);
        $perPage = $filters['per_page'] ?? 15;
        $lineIds = $this->resolveCandidateLineIds($filters['line'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null);

        if ($lineIds === []) {
            return new LengthAwarePaginator(
                [],
                0,
                $perPage,
                LengthAwarePaginator::resolveCurrentPage(),
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
            );
        }

        $serviceClass = $this->resolveServiceClass($filters['service_class'] ?? null);

        $items = $this->baseQuery($date, $lineIds)->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $serviceClass,
            ))
            ->filter()
            ->when($sellableOnly, fn (Collection $items) => $items->filter(fn (DepartureAvailabilityData $item): bool => $item->sellability->isSellable))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;
        $pageItems = $items->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * List sellable departures without pagination, for internal consumers that
     * need the full result set (e.g. counter-sale search). Not exposed publicly.
     *
     * @param  array{date?: string|null, line?: string|null, from?: string|null, to?: string|null, service_class?: string|null}  $filters
     * @return Collection<int, DepartureAvailabilityData>
     */
    public function searchSellable(array $filters, bool $sellableOnly = false): Collection
    {
        $date = $this->parseDateFilter($filters['date'] ?? null);
        $lineIds = $this->resolveCandidateLineIds($filters['line'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null);

        if ($lineIds === []) {
            return collect();
        }

        $serviceClass = $this->resolveServiceClass($filters['service_class'] ?? null);

        return $this->baseQuery($date, $lineIds)->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $serviceClass,
            ))
            ->filter()
            ->when($sellableOnly, fn (Collection $items) => $items->filter(fn (DepartureAvailabilityData $item): bool => $item->sellability->isSellable))
            ->values();
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Builder<Departure>
     */
    private function baseQuery(?CarbonImmutable $date, array $lineIds): Builder
    {
        return Departure::query()
            ->when(
                $date !== null,
                fn ($query) => $query->whereDate('departure_date', $date),
                fn ($query) => $query->where('departure_date', '>=', CarbonImmutable::now()->toDateString()),
            )
            ->where('status', DepartureStatus::Scheduled->value)
            ->whereIn('line_id', $lineIds)
            ->with([
                'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'bus.seats',
                'bus.composition',
                'bus.amenities',
            ])
            ->orderBy('departure_date')
            ->orderBy('departure_time');
    }

    private function parseDateFilter(?string $date): ?CarbonImmutable
    {
        return filled($date) ? CarbonImmutable::parse($date) : null;
    }

    /**
     * Resolves which lines can satisfy the requested segment, without loading
     * any departure. Lines are a small, cheap dataset compared to departures.
     *
     * @return array<int, int>
     */
    private function resolveCandidateLineIds(?string $lineUuid, ?string $from, ?string $to): array
    {
        return Line::query()
            ->where('status', 'active')
            ->when($lineUuid, fn ($query, string $uuid) => $query->where('uuid', $uuid))
            ->with(['stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order')])
            ->get()
            ->filter(function (Line $line) use ($from, $to): bool {
                if ($from === null && $to === null) {
                    return true;
                }

                // Accept the line if both cities exist as stops, regardless of
                // canonical stop_order. Return departures have reversed
                // stop_times, so a segment Lomé → Ouidah (stop_order 5 → 2)
                // is valid on a return departure even though the canonical
                // order is reversed. The per-departure resolution in
                // buildDepartureData will filter out departures that cannot
                // serve the requested segment.
                $stops = $line->getRelation('stops');
                $hasOrigin = $from === null || $stops->contains(fn (Stop $stop): bool => strtolower($stop->city) === strtolower($from));
                $hasDestination = $to === null || $stops->contains(fn (Stop $stop): bool => strtolower($stop->city) === strtolower($to));

                return $hasOrigin && $hasDestination;
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null, service_class?: string|null}  $filters
     */
    public function show(Departure $departure, array $filters): DepartureAvailabilityData
    {
        $departure->load([
            'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
            'bus.seats',
            'bus.composition',
            'bus.amenities',
        ]);

        return $this->buildDepartureData(
            $departure,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $this->resolveServiceClass($filters['service_class'] ?? null),
        );
    }

    /**
     * @return array{origin: Stop, destination: Stop}
     */
    public function resolveSegment(Departure $departure, string $originStopUuid, string $destinationStopUuid): array
    {
        $departure->loadMissing('line.stops');
        /** @var list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}> $stopTimes */
        $stopTimes = $departure->stop_times ?? [];

        return $stopTimes !== []
            ? $this->resolveLineSegmentStops->handleForDeparture(
                $departure->line,
                $stopTimes,
                originStopUuid: $originStopUuid,
                destinationStopUuid: $destinationStopUuid,
            )
            : $this->resolveLineSegmentStops->handle(
                $departure->line,
                originStopUuid: $originStopUuid,
                destinationStopUuid: $destinationStopUuid,
            );
    }

    /**
     * @return Collection<int, SeatAvailabilityData>
     */
    public function seats(Departure $departure, string $originStopUuid, string $destinationStopUuid): Collection
    {
        $departure->load([
            'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
            'bus.seats',
        ]);
        /** @var list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}> $stopTimes */
        $stopTimes = $departure->stop_times ?? [];

        $segment = $stopTimes !== []
            ? $this->resolveLineSegmentStops->handleForDeparture(
                $departure->line,
                $stopTimes,
                originStopUuid: $originStopUuid,
                destinationStopUuid: $destinationStopUuid,
            )
            : $this->resolveLineSegmentStops->handle(
                $departure->line,
                originStopUuid: $originStopUuid,
                destinationStopUuid: $destinationStopUuid,
            );

        return $this->buildSeatAvailability->handle($departure, $segment['origin'], $segment['destination']);
    }

    private function buildDepartureData(
        Departure $departure,
        ?string $from,
        ?string $to,
        ?ServiceClass $serviceClass = null,
    ): ?DepartureAvailabilityData {
        /** @var list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}> $stopTimes */
        $stopTimes = $departure->stop_times ?? [];

        try {
            $segment = $stopTimes !== []
                ? $this->resolveLineSegmentStops->handleForDeparture($departure->line, $stopTimes, $from, $to)
                : $this->resolveLineSegmentStops->handle($departure->line, $from, $to);
        } catch (ValidationException) {
            return null;
        }

        /** @var Stop $origin */
        $origin = $segment['origin'];
        /** @var Stop $destination */
        $destination = $segment['destination'];
        $availability = $this->buildSeatAvailability->handle($departure, $origin, $destination);

        $farePreview = $this->calculateFarePreview->handleForDeparture(
            $departure,
            $origin,
            $destination,
            serviceClass: $serviceClass,
        );

        // When a service class filter is active, drop departures that have no
        // fare for the requested class on this segment.
        if ($serviceClass !== null && $farePreview === null) {
            return null;
        }

        return new DepartureAvailabilityData(
            departure: $departure,
            originStop: $origin,
            destinationStop: $destination,
            availableSeatsCount: $availability->where('availabilityStatus', SeatAvailabilityStatus::Available)->count(),
            farePreview: $farePreview,
            sellability: $this->resolveDepartureSellability->handle($departure),
        );
    }

    private function resolveServiceClass(?string $code): ?ServiceClass
    {
        if ($code === null || $code === '') {
            return null;
        }

        return ServiceClass::query()->where('code', $code)->first();
    }
}
