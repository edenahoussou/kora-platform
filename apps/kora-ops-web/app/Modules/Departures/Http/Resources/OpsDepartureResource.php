<?php

namespace App\Modules\Departures\Http\Resources;

use App\Modules\BusNetwork\Data\LineType;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Models\Departure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin Departure
 */
class OpsDepartureResource extends JsonResource
{
    public function __construct($resource, private readonly ?string $manifestVersion = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => enum_value($this->status),
            'departureDate' => $this->departure_date?->toDateString(),
            'departureTime' => substr((string) $this->departure_time, 0, 5),
            'estimatedArrivalAt' => $this->estimated_arrival_at?->toISOString(),
            'line' => $this->whenLoaded('line', fn () => [
                'uuid' => $this->line->uuid,
                'code' => $this->line->code,
                'name' => $this->line->name,
                'type' => enum_value($this->line->type),
                'international' => $this->line->type === LineType::Internationale,
            ]),
            'stops' => $this->whenLoaded('line', fn () => $this->line->relationLoaded('stops')
                ? $this->tripStops()->map(fn ($stop) => [
                    'uuid' => $stop->uuid,
                    'name' => $stop->name,
                    'city' => $stop->city,
                    'country' => $stop->country,
                    'order' => $stop->stop_order,
                ])->values()
                : null),
            'bus' => $this->whenLoaded('bus', fn () => [
                'uuid' => $this->bus->uuid,
                'registrationNumber' => $this->bus->registration_number,
                'seatCount' => $this->bus->seat_count,
            ]),
            'seatMap' => $this->when(
                $this->bus?->relationLoaded('composition') === true,
                fn () => $this->bus->composition === null ? null : [
                    'compositionUuid' => $this->bus->composition->uuid,
                    'grid' => $this->bus->composition->seatGrid()->toArray(),
                    'seats' => $this->bus->seats->map(fn (Seat $seat): array => [
                        'code' => $seat->code,
                        'status' => enum_value($seat->status),
                        'sellable' => (bool) $seat->sellable,
                    ])->values(),
                ],
            ),
            'crew' => [
                'driver' => $this->whenLoaded('driver', fn () => $this->driver ? ['uuid' => $this->driver->uuid, 'name' => $this->driver->name] : null),
                'secondaryDriver' => $this->whenLoaded('secondaryDriver', fn () => $this->secondaryDriver ? ['uuid' => $this->secondaryDriver->uuid, 'name' => $this->secondaryDriver->name] : null),
                'conductor' => $this->whenLoaded('conductor', fn () => $this->conductor ? ['uuid' => $this->conductor->uuid, 'name' => $this->conductor->name] : null),
                'mechanic' => $this->whenLoaded('mechanic', fn () => $this->mechanic ? ['uuid' => $this->mechanic->uuid, 'name' => $this->mechanic->name] : null),
            ],
            'manifestVersion' => $this->manifestVersion,
        ];
    }
}
