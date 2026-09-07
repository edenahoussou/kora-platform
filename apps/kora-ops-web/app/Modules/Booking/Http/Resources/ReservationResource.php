<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
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
        $rawStatus = (string) $this->getRawOriginal('ticket_issuance_status');
        $ticketStatus = TicketIssuanceStatus::tryFrom($rawStatus) ?? TicketIssuanceStatus::NotStarted;

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'ticket_issuance_status' => $ticketStatus->value,
            'ticket_issued_at' => $this->ticket_issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'channel' => $this->channel?->value,
            'purchase_type' => $this->purchase_type->value,
            'group_size' => $this->group_size,
            'payer' => [
                'type' => $this->payer_type->value,
                'user_uuid' => $this->payer?->uuid,
                'company_account_uuid' => $this->companyAccount?->uuid,
            ],
            'departure_uuid' => $this->segments->first()?->departure?->uuid,
            'itinerary_quote_uuid' => $this->itineraryQuote?->uuid,
            'segments' => ReservationSegmentResource::collection($this->whenLoaded('segments')),
            'passengers' => ReservationPassengerResource::collection($this->whenLoaded('passengers')),
        ];
    }
}
