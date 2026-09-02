<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\ReservationPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationPassenger
 */
class ReservationPassengerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $firstSegment = $this->segments->first();

        return [
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'document_type' => $this->document_type?->value,
            'document_number' => $this->document_number,
            'nationality' => $this->nationality,
            'birth_date' => $this->birth_date?->toDateString(),
            'seat_code' => $firstSegment?->seat?->code,
            'segments' => $this->segments->map(fn ($segment): array => [
                'leg_order' => $segment->leg_order,
                'departure_uuid' => $segment->departure?->uuid,
                'seat_uuid' => $segment->seat?->uuid,
                'seat_code' => $segment->seat?->code,
                'status' => $segment->status->value,
            ])->values(),
        ];
    }
}
