<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDepartureAction
{
    /**
     * @param  array{line_uuid?: string, bus_uuid?: string, agency_uuid?: string, departure_date?: string, departure_time?: string, driver_uuid?: string|null, conductor_uuid?: string|null}  $attributes
     */
    public function execute(Departure $departure, array $attributes): Departure
    {
        $currentStatus = DepartureStatus::from((string) $departure->getRawOriginal('status'));
        if ($currentStatus !== DepartureStatus::Scheduled) {
            throw ValidationException::withMessages([
                'status' => 'Seuls les départs au statut PROGRAMMÉ peuvent être modifiés.',
            ]);
        }

        return DB::transaction(function () use ($departure, $attributes): Departure {
            $lineId = $departure->line_id;
            if (isset($attributes['line_uuid'])) {
                $lineId = Line::query()->where('uuid', $attributes['line_uuid'])->firstOrFail(['id'])->id;
            }
            $line = Line::query()->findOrFail($lineId);

            $busId = $departure->bus_id;
            if (isset($attributes['bus_uuid'])) {
                $busId = Bus::query()->where('uuid', $attributes['bus_uuid'])->firstOrFail(['id'])->id;
            }

            $agencyId = $departure->agency_id;
            if (isset($attributes['agency_uuid'])) {
                $agencyId = Agency::query()->where('uuid', $attributes['agency_uuid'])->firstOrFail(['id'])->id;
            }

            $driverId = $departure->driver_id;
            if (array_key_exists('driver_uuid', $attributes)) {
                $driverId = $attributes['driver_uuid'] !== null
                    ? User::query()->where('uuid', $attributes['driver_uuid'])->firstOrFail(['id'])->id
                    : null;
            }

            $conductorId = $departure->conductor_id;
            if (array_key_exists('conductor_uuid', $attributes)) {
                $conductorId = $attributes['conductor_uuid'] !== null
                    ? User::query()->where('uuid', $attributes['conductor_uuid'])->firstOrFail(['id'])->id
                    : null;
            }

            $date = $attributes['departure_date'] ?? $departure->departure_date->format('Y-m-d');
            $time = $attributes['departure_time'] ?? $departure->departure_time;
            $departureDateTime = CarbonImmutable::parse($date.' '.$time);
            $estimatedArrivalAt = $line->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $line->estimated_duration_minutes)
                : null;

            $departure->update([
                'line_id' => $lineId,
                'bus_id' => $busId,
                'agency_id' => $agencyId,
                'departure_date' => $date,
                'departure_time' => $time,
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $driverId,
                'conductor_id' => $conductorId,
            ]);

            return $departure->refresh();
        });
    }
}
