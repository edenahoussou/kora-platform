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
     * @param  array{date: string, line?: string|null, from?: string|null, to?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, DepartureAvailabilityData>
     */
    public function search(array $filters, bool $sellableOnly = false): LengthAwarePaginator
    {
        $date = CarbonImmutable::parse($filters['date']);
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

        $paginator = $this->baseQuery($date, $lineIds)->paginate($perPage);

        $items = $paginator->getCollection()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $date,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ))
            ->filter()
            ->when($sellableOnly, fn (Collection $items) => $items->filter(fn (DepartureAvailabilityData $item): bool => $item->sellability->isSellable))
            ->values();

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * List sellable departures without pagination, for internal consumers that
     * need the full result set (e.g. counter-sale search). Not exposed publicly.
     *
     * @param  array{date: string, line?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Collection<int, DepartureAvailabilityData>
     */
    public function searchSellable(array $filters, bool $sellableOnly = false): Collection
    {
        $date = CarbonImmutable::parse($filters['date']);
        $lineIds = $this->resolveCandidateLineIds($filters['line'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null);

        if ($lineIds === []) {
            return collect();
        }

        return $this->baseQuery($date, $lineIds)->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $date,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ))
            ->filter()
            ->when($sellableOnly, fn (Collection $items) => $items->filter(fn (DepartureAvailabilityData $item): bool => $item->sellability->isSellable))
            ->values();
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Builder<Departure>
     */
    private function baseQuery(CarbonImmutable $date, array $lineIds): Builder
    {
        return Departure::query()
            ->whereDate('departure_date', $date)
            ->where('status', DepartureStatus::Scheduled->value)
            ->whereIn('line_id', $lineIds)
            ->with([
                'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'bus.seats',
            ])
            ->orderBy('departure_time');
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
                try {
                    $this->resolveLineSegmentStops->handle($line, $from, $to);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    public function show(Departure $departure, array $filters): DepartureAvailabilityData
    {
        $departure->load([
            'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
            'bus.seats',
        ]);

        return $this->buildDepartureData(
            $departure,
            CarbonImmutable::parse((string) $departure->departure_date),
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        );
    }

    /**
     * @return array{origin: Stop, destination: Stop}
     */
    public function resolveSegment(Departure $departure, string $originStopUuid, string $destinationStopUuid): array
    {
        $departure->loadMissing('line.stops');

        return $this->resolveLineSegmentStops->handle(
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

        $segment = $this->resolveLineSegmentStops->handle(
            $departure->line,
            originStopUuid: $originStopUuid,
            destinationStopUuid: $destinationStopUuid,
        );

        return $this->buildSeatAvailability->handle($departure, $segment['origin'], $segment['destination']);
    }

    private function buildDepartureData(
        Departure $departure,
        CarbonImmutable $date,
        ?string $from,
        ?string $to,
    ): ?DepartureAvailabilityData {
        try {
            $segment = $this->resolveLineSegmentStops->handle($departure->line, $from, $to);
        } catch (ValidationException) {
            return null;
        }

        /** @var Stop $origin */
        $origin = $segment['origin'];
        /** @var Stop $destination */
        $destination = $segment['destination'];
        $availability = $this->buildSeatAvailability->handle($departure, $origin, $destination);

        return new DepartureAvailabilityData(
            departure: $departure,
            originStop: $origin,
            destinationStop: $destination,
            availableSeatsCount: $availability->where('availabilityStatus', SeatAvailabilityStatus::Available)->count(),
            farePreview: $this->calculateFarePreview->handle($departure->line, $origin, $destination, $date),
            sellability: $this->resolveDepartureSellability->handle($departure),
        );
    }
}
