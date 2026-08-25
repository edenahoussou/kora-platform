<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'channel' => $this->channel?->value,
            'departure_uuid' => $this->departure?->uuid,
            'price_quote_uuid' => $this->priceQuote?->uuid,
            'segments' => ReservationSegmentResource::collection($this->whenLoaded('segments')),
            'passengers' => ReservationPassengerResource::collection($this->whenLoaded('passengers')),
        ];
    }
}
