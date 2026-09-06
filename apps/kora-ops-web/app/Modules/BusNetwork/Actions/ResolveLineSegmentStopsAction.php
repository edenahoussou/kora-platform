<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResolveLineSegmentStopsAction
{
    /**
     * Resolve origin and destination stops for a line using stop_order.
     * Used when no departure context is available (line candidate filtering).
     *
     * @return array{origin: Stop, destination: Stop}
     */
    public function handle(
        Line $line,
        ?string $from = null,
        ?string $to = null,
        ?string $originStopUuid = null,
        ?string $destinationStopUuid = null,
        ?string $originPhysicalStopUuid = null,
        ?string $destinationPhysicalStopUuid = null,
    ): array {
        $line->loadMissing(['stops' => fn ($query) => $query
            ->where('status', StopStatus::Active->value)
            ->orderBy('stop_order')]);

        /** @var EloquentCollection<int, Stop> $stops */
        $stops = $line->getRelation('stops');

        $origin = $this->resolveStop($stops, $from, $originStopUuid, $originPhysicalStopUuid, true);
        $destination = $this->resolveStop($stops, $to, $destinationStopUuid, $destinationPhysicalStopUuid, false);

        if (! $origin || ! $destination || $origin->stop_order >= $destination->stop_order) {
            throw ValidationException::withMessages([
                'segment' => ['The requested origin and destination do not form a valid ordered segment for this line.'],
            ]);
        }

        return ['origin' => $origin, 'destination' => $destination];
    }

    /**
     * Resolve origin and destination stops using the departure's stop_times
     * order instead of the line's stop_order. This supports return departures
     * where the circulation order is reversed relative to the line's stop_order.
     *
     * @param  list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}>  $stopTimes
     * @return array{origin: Stop, destination: Stop}
     */
    public function handleForDeparture(
        Line $line,
        array $stopTimes,
        ?string $from = null,
        ?string $to = null,
        ?string $originStopUuid = null,
        ?string $destinationStopUuid = null,
        ?string $originPhysicalStopUuid = null,
        ?string $destinationPhysicalStopUuid = null,
    ): array {
        $line->loadMissing(['stops' => fn ($query) => $query
            ->where('status', StopStatus::Active->value)
            ->orderBy('stop_order')]);

        /** @var EloquentCollection<int, Stop> $stops */
        $stops = $line->getRelation('stops');

        // Build an ordered stop list from the departure's stop_times.
        // The stop_times array defines the actual circulation order for this
        // departure, which may differ from the line's stop_order (e.g. return).
        $orderedStops = new EloquentCollection;
        $stopOrderInDeparture = [];
        foreach ($stopTimes as $index => $stopTime) {
            $stop = $stops->first(fn (Stop $s): bool => $s->uuid === ($stopTime['stop_uuid'] ?? null));
            if ($stop) {
                $orderedStops->push($stop);
                $stopOrderInDeparture[$stop->uuid] = $index;
            }
        }

        if ($orderedStops->count() < 2) {
            throw ValidationException::withMessages([
                'segment' => ['The departure does not have enough stops to form a segment.'],
            ]);
        }

        $origin = $this->resolveStop($orderedStops, $from, $originStopUuid, $originPhysicalStopUuid, true);
        $destination = $this->resolveStop($orderedStops, $to, $destinationStopUuid, $destinationPhysicalStopUuid, false);

        if (! $origin || ! $destination) {
            throw ValidationException::withMessages([
                'segment' => ['The requested origin and destination do not match any stop on this departure.'],
            ]);
        }

        $originOrder = $stopOrderInDeparture[$origin->uuid] ?? 0;
        $destinationOrder = $stopOrderInDeparture[$destination->uuid] ?? PHP_INT_MAX;

        if ($originOrder >= $destinationOrder) {
            throw ValidationException::withMessages([
                'segment' => ['The requested origin and destination do not form a valid ordered segment for this departure.'],
            ]);
        }

        return ['origin' => $origin, 'destination' => $destination];
    }

    /**
     * @param  EloquentCollection<int, Stop>  $stops
     */
    private function resolveStop(
        EloquentCollection $stops,
        ?string $city,
        ?string $stopUuid,
        ?string $physicalStopUuid,
        bool $first,
    ): ?Stop {
        if ($stopUuid) {
            return $stops->first(fn (Stop $stop): bool => $stop->uuid === $stopUuid);
        }

        if ($physicalStopUuid) {
            $physicalStop = PhysicalStop::query()->where('uuid', $physicalStopUuid)->first();
            if ($physicalStop) {
                return $stops->first(fn (Stop $stop): bool => $stop->physical_stop_id === $physicalStop->id);
            }
        }

        if ($city) {
            return $stops->first(fn (Stop $stop): bool => Str::lower($stop->city) === Str::lower($city));
        }

        return $first ? $stops->first() : $stops->last();
    }
}
