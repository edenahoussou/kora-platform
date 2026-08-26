<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Http\Resources\LineResource;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

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
            'price_quote_uuid' => $this->priceQuote?->uuid,
            'passenger_count' => $this->when(
                $this->relationLoaded('segments'),
                fn (): int => $this->segments->count(),
            ),
            'departure' => $this->when(
                $this->relationLoaded('departure'),
                fn (): array => [
                    'uuid' => $this->departure->uuid,
                    'departure_date' => $this->departure->departure_date?->toDateString(),
                    'departure_time' => $this->departure->formattedDepartureTime(),
                    'estimated_arrival_at' => $this->departure->estimated_arrival_at?->toIso8601String(),
                    'status' => enum_value($this->departure->status),
                    'line' => $this->when(
                        $this->departure->relationLoaded('line'),
                        fn (): LineResource => new LineResource($this->departure->line),
                    ),
                ],
            ),
            'segments' => ReservationSegmentResource::collection($this->whenLoaded('segments')),
            'passengers' => ReservationPassengerResource::collection($this->whenLoaded('passengers')),
        ];
    }
}
