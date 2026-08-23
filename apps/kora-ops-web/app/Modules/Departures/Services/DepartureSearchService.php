<?php

namespace App\Modules\Departures\Services;

use App\Modules\BusNetwork\Actions\ResolveLineSegmentStopsAction;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureAvailabilityData;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateFarePreviewAction;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\SeatAvailabilityData;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DepartureSearchService
{
    public function __construct(
        private ResolveLineSegmentStopsAction $resolveLineSegmentStops,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private CalculateFarePreviewAction $calculateFarePreview,
    ) {}

    /**
     * @param  array{date: string, line?: string|null, from?: string|null, to?: string|null, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, DepartureAvailabilityData>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $date = CarbonImmutable::parse($filters['date']);
        $perPage = $filters['per_page'] ?? 15;

        $results = Departure::query()
            ->whereDate('departure_date', $date)
            ->where('status', DepartureStatus::Scheduled->value)
            ->when(
                $filters['line'] ?? null,
                fn ($query, string $lineUuid) => $query->whereHas('line', fn ($line) => $line->where('uuid', $lineUuid)),
            )
            ->with([
                'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'bus.seats',
            ])
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $date,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ))
            ->filter()
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
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
        );
    }
}
