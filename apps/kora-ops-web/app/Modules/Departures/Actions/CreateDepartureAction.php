<?php

namespace App\Modules\Departures\Actions;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateDepartureAction
{
    /**
     * @param  array{line_id: int, bus_id: int, agency_id: int, departure_date: string, departure_time: string, driver_id?: int|null, conductor_id?: int|null, created_by?: int|null}  $attributes
     */
    public function execute(array $attributes): Departure
    {
        return DB::transaction(function () use ($attributes): Departure {
            $line = Line::query()->findOrFail($attributes['line_id']);
            $bus = Bus::query()->with('agency')->findOrFail($attributes['bus_id']);

            $departureDateTime = CarbonImmutable::parse($attributes['departure_date'].' '.$attributes['departure_time']);
            $estimatedArrivalAt = $line->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $line->estimated_duration_minutes)
                : null;

            return Departure::query()->create([
                'line_id' => $attributes['line_id'],
                'bus_id' => $attributes['bus_id'],
                'agency_id' => $attributes['agency_id'],
                'departure_date' => $attributes['departure_date'],
                'departure_time' => $attributes['departure_time'],
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $attributes['driver_id'] ?? null,
                'conductor_id' => $attributes['conductor_id'] ?? null,
                'created_by' => $attributes['created_by'] ?? null,
                'status' => DepartureStatus::Scheduled->value,
            ]);
        });
    }
}
