<?php

namespace App\Modules\Departures\Http\Resources;

use App\Modules\BusNetwork\Http\Resources\LineResource;
use App\Modules\BusNetwork\Http\Resources\StopResource;
use App\Modules\Departures\Data\DepartureAvailabilityData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

class DepartureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DepartureAvailabilityData $data */
        $data = $this->resource;
        $departure = $data->departure;

        return [
            'uuid' => $departure->uuid,
            'departure_date' => CarbonImmutable::parse((string) $departure->departure_date)->toDateString(),
            'departure_time' => $departure->departure_time,
            'estimated_arrival_at' => $departure->estimated_arrival_at
                ? CarbonImmutable::parse((string) $departure->estimated_arrival_at)->toISOString()
                : null,
            'status' => enum_value($departure->status),
            'line' => new LineResource($departure->line),
            'bus' => [
                'uuid' => $departure->bus->uuid,
                'registration_number' => $departure->bus->registration_number,
                'brand' => $departure->bus->brand,
                'model' => $departure->bus->model,
                'seat_count' => $departure->bus->seat_count,
            ],
            'origin_stop' => new StopResource($data->originStop),
            'destination_stop' => new StopResource($data->destinationStop),
            'available_seats_count' => $data->availableSeatsCount,
            'fare_preview' => $data->farePreview,
        ];
    }
}
