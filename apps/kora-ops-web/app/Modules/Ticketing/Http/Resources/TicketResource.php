<?php

namespace App\Modules\Ticketing\Http\Resources;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
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
        $segments = $passenger === null ? collect() : $passenger->segments;

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
            'segments' => $segments->map(fn ($segment): array => [
                ...$this->segmentSchedule($segment),
                'leg_order' => $segment->leg_order,
                'departure_uuid' => $segment->departure?->uuid,
                'origin_stop_uuid' => $segment->originStop?->uuid,
                'destination_stop_uuid' => $segment->destinationStop?->uuid,
                'seat_uuid' => $segment->seat?->uuid,
                'seat_code' => $segment->seat?->code,
                'status' => $segment->status->value,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function segmentSchedule(ReservationSegment $segment): array
    {
        $schedule = app(DepartureStopTimeResolver::class)->displaySchedule($segment->departure, $segment->originStop, $segment->destinationStop);

        return [
            'departure_at' => $schedule['departureAt'],
            'arrival_at' => $schedule['arrivalAt'],
            'departure_timezone' => $schedule['departureTimezone'],
            'arrival_timezone' => $schedule['arrivalTimezone'],
        ];
    }
}
