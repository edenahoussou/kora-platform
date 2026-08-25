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
            'seat_uuid' => $this->seat?->uuid,
            'seat_code' => $this->seat?->code,
            'origin_stop_uuid' => $this->originStop?->uuid,
            'origin_stop' => $this->originStop?->name,
            'destination_stop_uuid' => $this->destinationStop?->uuid,
            'destination_stop' => $this->destinationStop?->name,
            'status' => $this->status->value,
        ];
    }
}
