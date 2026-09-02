<?php

namespace App\Modules\Booking\Services;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;

/**
 * Resolves published stop offsets to absolute UTC instants.
 */
final class DepartureStopTimeResolver
{
    public function departureAt(Departure $departure, Stop $stop): ?CarbonImmutable
    {
        return $this->resolve($departure, $stop, 'departure_minutes')
            ?? ($stop->stop_order === 1 ? $departure->scheduledDepartureAt()->utc() : null);
    }

    public function arrivalAt(Departure $departure, Stop $stop): ?CarbonImmutable
    {
        $resolved = $this->resolve($departure, $stop, 'arrival_minutes');
        if ($resolved !== null) {
            return $resolved;
        }

        $stops = $departure->line->relationLoaded('stops')
            ? $departure->line->stops
            : $departure->line->stops()->orderBy('stop_order')->get();

        if ($stops->last()?->id === $stop->id && $departure->estimated_arrival_at !== null) {
            return CarbonImmutable::instance($departure->estimated_arrival_at)->utc();
        }

        return null;
    }

    private function resolve(Departure $departure, Stop $stop, string $field): ?CarbonImmutable
    {
        foreach ($departure->stop_times ?? [] as $stopTime) {
            if (($stopTime['stop_uuid'] ?? null) !== $stop->uuid) {
                continue;
            }

            $minutes = $stopTime[$field] ?? null;
            $timezone = $stopTime['timezone'] ?? $departure->departure_timezone ?? config('app.timezone');

            if (! is_int($minutes) && ! (is_string($minutes) && is_numeric($minutes))) {
                return null;
            }

            return $departure->scheduledDepartureAt()->utc()->addMinutes((int) $minutes);
        }

        return null;
    }
}
