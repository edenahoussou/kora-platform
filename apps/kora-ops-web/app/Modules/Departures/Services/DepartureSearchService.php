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
     * @param  array{date: string|null, from?: string|null, to?: string|null}  $filters
     * @return Collection<int, DepartureAvailabilityData>
     */
    public function search(array $filters): Collection
    {
        $date = $filters['date'] !== null
            ? CarbonImmutable::parse($filters['date'])
            : null;
        $today = CarbonImmutable::today();
        $currentTime = now()->format('H:i:s');

        return Departure::query()
            ->where('status', DepartureStatus::Scheduled->value)
            ->with([
                'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'bus.seats',
            ])
            ->when(
                $date !== null,
                fn ($query) => $query->whereDate('departure_date', $date),
                fn ($query) => $query
                    ->where(function ($query) use ($today, $currentTime): void {
                        $query
                            ->whereDate('departure_date', '>', $today)
                            ->orWhere(function ($query) use ($today, $currentTime): void {
                                $query
                                    ->whereDate('departure_date', $today)
                                    ->whereTime('departure_time', '>=', $currentTime);
                            });
                    }),
            )
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $date ?? CarbonImmutable::parse((string) $departure->departure_date),
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ))
            ->filter()
            ->values();
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
