<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelSouteBudget;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computes whether a parcel deposit location can reach a destination hub.
 *
 * Structural reachability is a configuration-time invariant: both stops must
 * belong to the same line and the origin stop must precede the destination
 * stop in the line order. Operational availability is a separate, never
 * persisted calculation over scheduled departures inside the routing horizon.
 */
class ParcelRouting
{
    public function __construct(private OperationsSettings $settings) {}

    public function horizonDays(): int
    {
        return max(1, (int) $this->settings->parcelRoutingHorizonDays);
    }

    public function isActiveHub(ParcelHub $hub): bool
    {
        return $hub->enabled && ! $hub->isArchived();
    }

    public function isStructurallyRoutable(ParcelHub $origin, ParcelHub $destination): bool
    {
        $originStop = $origin->stop;
        $destinationStop = $destination->stop;

        return $originStop !== null && $destinationStop !== null
            && $originStop->line_id === $destinationStop->line_id
            && (int) $originStop->stop_order < (int) $destinationStop->stop_order;
    }

    public function assertStructurallyRoutable(ParcelHub $origin, ParcelHub $destination): void
    {
        abort_unless($this->isActiveHub($origin) && $this->isActiveHub($destination), 409, 'Deposit locations must be enabled and not archived.');
        abort_unless($this->isStructurallyRoutable($origin, $destination), 409, 'Corridor has no line serving both stops in the required order.');
    }

    public function assertDepositMatchesCorridor(ParcelHub $depositHub, ParcelCorridor $corridor): void
    {
        abort_unless($corridor->origin_hub_id === $depositHub->id, 409, 'Deposit location must be the corridor origin hub; implicit transfers are not supported.');
    }

    public function assertOperationallyAvailable(ParcelHub $origin, ParcelHub $destination, int $grams = 0): void
    {
        $this->assertStructurallyRoutable($origin, $destination);
        $availability = $this->availability($origin, $destination);
        abort_if($availability->isEmpty(), 409, 'No scheduled departure serves this corridor within the routing horizon.');

        $hasCapacity = $availability->contains(function (array $row) use ($grams): bool {
            $capacity = $row['capacity_grams'];

            return $capacity === null || ((int) $capacity - (int) $row['reserved_grams']) >= $grams;
        });
        abort_unless($hasCapacity, 409, 'No scheduled departure has enough parcel capacity for this weight.');
    }

    /** @return Collection<int, Departure> */
    public function departures(ParcelHub $origin, ParcelHub $destination, ?CarbonInterface $from = null): Collection
    {
        $originStop = $origin->stop;
        $destinationStop = $destination->stop;

        if ($originStop === null || $destinationStop === null) {
            return collect();
        }

        $from ??= now();
        $windowEnd = $from->copy()->addDays($this->horizonDays());

        return $this->query($originStop->line_id, $from, $windowEnd)
            ->filter(fn (Departure $departure): bool => $this->departureServes($departure, (int) $originStop->id, (int) $destinationStop->id, $from))
            ->sortBy(fn (Departure $departure): int => $departure->scheduledDepartureAt()->getTimestamp())
            ->values();
    }

    /**
     * Single query for every corridor of a listing: keeps the public corridor
     * endpoint free of N+1 queries.
     *
     * @param  Collection<int, ParcelCorridor>  $corridors
     * @return Collection<int, ParcelCorridor>
     */
    public function filterOperationallyAvailable(Collection $corridors, ?CarbonInterface $from = null): Collection
    {
        $from ??= now();
        $lineIds = $corridors->map(fn (ParcelCorridor $corridor): ?int => $corridor->originHub?->stop?->line_id)->filter()->unique()->values();

        if ($lineIds->isEmpty()) {
            return $corridors->take(0);
        }

        $departures = $this->query($lineIds->all(), $from, $from->copy()->addDays($this->horizonDays()));

        return $corridors->filter(function (ParcelCorridor $corridor) use ($departures, $from): bool {
            if (! $corridor->enabled || ! $corridor->originHub || ! $corridor->destinationHub
                || ! $this->isActiveHub($corridor->originHub) || ! $this->isActiveHub($corridor->destinationHub)) {
                return false;
            }

            $originStopId = (int) $corridor->originHub->stop?->id;
            $destinationStopId = (int) $corridor->destinationHub->stop?->id;

            return $departures->contains(fn (Departure $departure): bool => $departure->line_id === $corridor->originHub->stop?->line_id
                && $this->departureServes($departure, $originStopId, $destinationStopId, $from));
        })->values();
    }

    /**
     * @return Collection<int, array{departure: Departure, capacity_grams: int|null, reserved_grams: int}>
     */
    public function availability(ParcelHub $origin, ParcelHub $destination, ?CarbonInterface $from = null): Collection
    {
        $departures = $this->departures($origin, $destination, $from);
        $departureIds = $departures->pluck('id')->all();

        if ($departureIds === []) {
            return collect();
        }

        $budgets = ParcelSouteBudget::query()->whereIn('departure_id', $departureIds)->get()->keyBy('departure_id');
        $reserved = ParcelAssignment::query()->whereIn('departure_id', $departureIds)->whereNull('released_at')
            ->join('parcels', 'parcels.id', '=', 'parcel_assignments.parcel_id')
            ->selectRaw('parcel_assignments.departure_id as departure_id, COALESCE(SUM(parcels.weight_grams), 0) as grams')
            ->groupBy('parcel_assignments.departure_id')
            ->pluck('grams', 'departure_id');

        return $departures->map(fn (Departure $departure): array => [
            'departure' => $departure,
            'capacity_grams' => $budgets->get($departure->id)?->capacity_grams,
            'reserved_grams' => (int) ($reserved[$departure->id] ?? 0),
        ]);
    }

    /**
     * @param  int|array<int, int>  $lineIds
     * @return Collection<int, Departure>
     */
    private function query(int|array $lineIds, CarbonInterface $from, CarbonInterface $until): Collection
    {
        return Departure::query()
            ->whereIn('line_id', (array) $lineIds)
            ->where('status', DepartureStatus::Scheduled)
            ->whereDate('departure_date', '>=', $from->toDateString())
            ->whereDate('departure_date', '<=', $until->toDateString())
            ->with(['line.stops'])
            ->get();
    }

    private function departureServes(Departure $departure, int $originStopId, int $destinationStopId, CarbonInterface $from): bool
    {
        $tripStopIds = $departure->tripStops()->pluck('id');
        $originIndex = $tripStopIds->search($originStopId, true);
        $destinationIndex = $tripStopIds->search($destinationStopId, true);

        return $originIndex !== false && $destinationIndex !== false
            && $originIndex < $destinationIndex
            && $departure->scheduledDepartureAt()->greaterThanOrEqualTo($from);
    }
}
