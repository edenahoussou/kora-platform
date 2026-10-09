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

        if ($departure->tripStops()->last()?->id === $stop->id && $departure->estimated_arrival_at !== null) {
            return CarbonImmutable::instance($departure->estimated_arrival_at)->utc();
        }

        return null;
    }

    /**
     * Return the 0-based position of the stop in the departure's circulation
     * order (the order defined by stop_times, which may be reversed for
     * return trips). Falls back to the line's canonical stop_order when
     * stop_times is empty — for forward departures without explicit
     * stop_times, the canonical order is the circulation order.
     */
    public function circulationIndexOf(Departure $departure, Stop $stop): int
    {
        $stopTimes = $departure->stop_times ?? [];

        if ($stopTimes === []) {
            return $stop->stop_order;
        }

        foreach ($stopTimes as $index => $stopTime) {
            if (($stopTime['stop_uuid'] ?? null) === $stop->uuid) {
                return $index;
            }
        }

        return $stop->stop_order;
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
