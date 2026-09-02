<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\ReservationSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationSegment
 */
class ReservationSegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'leg_order' => $this->leg_order,
            'passenger_index' => $this->passenger_index,
            'departure_uuid' => $this->departure?->uuid,
            'seat_uuid' => $this->seat?->uuid,
            'seat_code' => $this->seat?->code,
            'origin_stop' => $this->originStop === null ? null : [
                'uuid' => $this->originStop->uuid,
                'name' => $this->originStop->name,
            ],
            'destination_stop' => $this->destinationStop === null ? null : [
                'uuid' => $this->destinationStop->uuid,
                'name' => $this->destinationStop->name,
            ],
            'status' => $this->status->value,
        ];
    }
}
