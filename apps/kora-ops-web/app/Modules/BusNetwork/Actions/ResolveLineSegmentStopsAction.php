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
