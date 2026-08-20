<?php

namespace App\Modules\Departures\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDepartureAction
{
    /**
     * @param  array{line_id?: int, bus_id?: int, agency_id?: int, departure_date?: string, departure_time?: string, driver_id?: int|null, conductor_id?: int|null}  $attributes
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
            $lineId = $attributes['line_id'] ?? $departure->line_id;
            $line = Line::query()->findOrFail($lineId);

            $date = $attributes['departure_date'] ?? $departure->departure_date->format('Y-m-d');
            $time = $attributes['departure_time'] ?? $departure->departure_time;
            $departureDateTime = CarbonImmutable::parse($date.' '.$time);
            $estimatedArrivalAt = $line->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $line->estimated_duration_minutes)
                : null;

            $departure->update([
                'line_id' => $attributes['line_id'] ?? $departure->line_id,
                'bus_id' => $attributes['bus_id'] ?? $departure->bus_id,
                'agency_id' => $attributes['agency_id'] ?? $departure->agency_id,
                'departure_date' => $date,
                'departure_time' => $time,
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => array_key_exists('driver_id', $attributes) ? $attributes['driver_id'] : $departure->driver_id,
                'conductor_id' => array_key_exists('conductor_id', $attributes) ? $attributes['conductor_id'] : $departure->conductor_id,
            ]);

            return $departure->refresh();
        });
    }
}
