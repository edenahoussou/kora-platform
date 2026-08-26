<?php

namespace App\Modules\Ticketing\Http\Resources;

use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $passenger = $this->reservationPassenger;
        $segment = $passenger?->segments?->first();

        return [
            'uuid' => $this->uuid,
            'short_code' => $this->short_code,
            'qr_code' => $this->qr_code,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'reservation_uuid' => $this->reservation?->uuid,
            'passenger' => $passenger === null ? null : [
                'uuid' => $passenger->uuid,
                'first_name' => $passenger->first_name,
                'last_name' => $passenger->last_name,
            ],
            'seat_code' => $segment?->seat?->code,
        ];
    }
}
