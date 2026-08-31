<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\PublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateDepartureAction
{
    public function __construct(private PublicationService $publication) {}

    /**
     * @param  array{line_uuid: string, bus_uuid: string, departure_date: string, departure_time: string, driver_uuid?: string|null, conductor_uuid?: string|null, created_by?: int|null}  $attributes
     */
    public function execute(array $attributes): Departure
    {
        return DB::transaction(function () use ($attributes): Departure {
            $line = Line::query()->where('uuid', $attributes['line_uuid'])->firstOrFail();
            $bus = Bus::query()->where('uuid', $attributes['bus_uuid'])->firstOrFail();

            $driverId = null;
            if (isset($attributes['driver_uuid'])) {
                $driverId = User::query()->where('uuid', $attributes['driver_uuid'])->firstOrFail(['id'])->id;
            }

            $conductorId = null;
            if (isset($attributes['conductor_uuid'])) {
                $conductorId = User::query()->where('uuid', $attributes['conductor_uuid'])->firstOrFail(['id'])->id;
            }

            $departureDateTime = CarbonImmutable::parse($attributes['departure_date'].' '.$attributes['departure_time']);
            $estimatedArrivalAt = $line->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $line->estimated_duration_minutes)
                : null;

            $departure = new Departure([
                'line_id' => $line->id,
                'bus_id' => $bus->id,
                'departure_date' => $attributes['departure_date'],
                'departure_time' => $attributes['departure_time'],
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $driverId,
                'conductor_id' => $conductorId,
                'created_by' => $attributes['created_by'] ?? null,
                'status' => DepartureStatus::Scheduled->value,
            ]);
            $this->publication->guardOperationalResources($departure);
            $departure->save();

            return $departure;
        });
    }
}
