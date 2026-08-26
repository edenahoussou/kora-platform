<?php

namespace App\Modules\Departures\Http\Resources;

use App\Modules\SeatInventory\Data\SeatAvailabilityData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

class SeatAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeatAvailabilityData $data */
        $data = $this->resource;

        return [
            'uuid' => $data->seat->uuid,
            'code' => $data->seat->code,
            'type' => enum_value($data->seat->type),
            'sellable' => (bool) $data->seat->sellable,
            'availability_status' => enum_value($data->availabilityStatus),
            'held_until' => $data->heldUntil?->toISOString(),
        ];
    }
}
