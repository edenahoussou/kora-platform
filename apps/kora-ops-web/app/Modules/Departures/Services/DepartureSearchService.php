<?php

namespace App\Modules\Departures\Services;

use App\Modules\BusNetwork\Actions\ResolveLineSegmentStopsAction;
use App\Modules\BusNetwork\Data\StopStatus;
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
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DepartureSearchService
{
    public function __construct(
        private ResolveLineSegmentStopsAction $resolveLineSegmentStops,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private CalculateFarePreviewAction $calculateFarePreview,
        private ResolveDepartureSellabilityAction $resolveSellability,
    ) {}

    /**
     * @param  array{date: string|null, from?: string|null, to?: string|null}  $filters
     * @return Collection<int, DepartureAvailabilityData>
     */
    public function search(array $filters, bool $sellableOnly = false): Collection
    {
        $date = $filters['date'] !== null
            ? CarbonImmutable::parse($filters['date'])
            : null;
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();

        return Departure::query()
            ->when(
                $sellableOnly,
                fn ($query) => $query->whereIn('status', $this->sellableStatuses()),
                fn ($query) => $query->where('status', DepartureStatus::Scheduled->value),
            )
            ->with([
                'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'bus.seats',
                'agency:id,uuid',
            ])
            ->when(
                $date !== null,
                fn ($query) => $query->whereDate('departure_date', $date),
                fn ($query) => $query
                    ->whereDate('departure_date', '>=', $today),
            )
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Departure $departure): ?DepartureAvailabilityData => $this->buildDepartureData(
                $departure,
                $date ?? CarbonImmutable::parse((string) $departure->departure_date),
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $sellableOnly,
                $now,
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
            'agency:id,uuid',
        ]);

        $data = $this->buildDepartureData(
            $departure,
            CarbonImmutable::parse((string) $departure->departure_date),
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            false,
            CarbonImmutable::now(config('app.timezone')),
        );

        if ($data === null) {
            throw ValidationException::withMessages([
                'departure' => 'Le départ ne correspond pas au trajet demandé.',
            ]);
        }

        return $data;
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

        $segment = $this->resolveSegment($departure, $originStopUuid, $destinationStopUuid);

        return $this->buildSeatAvailability->handle($departure, $segment['origin'], $segment['destination']);
    }

    /**
     * @return array{origin: Stop, destination: Stop}
     */
    public function resolveSegment(Departure $departure, string $originStopUuid, string $destinationStopUuid): array
    {
        $departure->loadMissing([
            'line.stops' => fn ($query) => $query->where('status', StopStatus::Active->value)->orderBy('stop_order'),
        ]);

        return $this->resolveLineSegmentStops->handle(
            $departure->line,
            originStopUuid: $originStopUuid,
            destinationStopUuid: $destinationStopUuid,
        );
    }

    private function buildDepartureData(
        Departure $departure,
        CarbonImmutable $date,
        ?string $from,
        ?string $to,
        bool $sellableOnly,
        CarbonImmutable $now,
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
        $sellability = $this->resolveSellability->handle($departure, $now);

        if ($sellableOnly && ! $sellability->isSellable) {
            return null;
        }

        return new DepartureAvailabilityData(
            departure: $departure,
            originStop: $origin,
            destinationStop: $destination,
            availableSeatsCount: $availability->where('availabilityStatus', SeatAvailabilityStatus::Available)->count(),
            farePreview: $this->calculateFarePreview->handle($departure->line, $origin, $destination, $date),
            sellability: $sellability,
        );
    }

    /** @return list<string> */
    private function sellableStatuses(): array
    {
        $statuses = config('kora.departures.sellability.sellable_statuses', ['scheduled']);

        return is_array($statuses)
            ? array_values(array_filter($statuses, 'is_string'))
            : [DepartureStatus::Scheduled->value];
    }
}
