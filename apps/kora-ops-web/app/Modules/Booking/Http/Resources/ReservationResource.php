<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\Reservation;
<<<<<<< HEAD
<<<<<<< HEAD
use App\Modules\BusNetwork\Http\Resources\LineResource;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
=======
>>>>>>> 752c4ce (feat: implement bus amenity management system)
=======
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
>>>>>>> ce91d81 (Refactor code structure for improved readability and maintainability)
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
        $ticketIssuanceStatus = TicketIssuanceStatus::tryFrom((string) $this->getRawOriginal('ticket_issuance_status'));

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'ticket_issuance_status' => $ticketIssuanceStatus instanceof TicketIssuanceStatus
                ? $ticketIssuanceStatus->value
                : TicketIssuanceStatus::NotStarted->value,
            'ticket_issued_at' => $this->ticket_issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'channel' => $this->channel?->value,
            'departure_uuid' => $this->departure?->uuid,
            'segments' => ReservationSegmentResource::collection($this->whenLoaded('segments')),
            'passengers' => ReservationPassengerResource::collection($this->whenLoaded('passengers')),
        ];
    }
}
