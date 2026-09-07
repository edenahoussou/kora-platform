<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Modules\Booking\Actions\ResolvePassengerJourneyStatusAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class TicketsController
{
    public function __construct(
        private readonly ResolvePassengerJourneyStatusAction $resolveJourneyStatus,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);

        $query = Ticket::query()
            ->with([
                'reservationPassenger',
                'reservation.payments' => fn ($q) => $q->orderByDesc('created_at'),
                'reservation.passengers:id,reservation_id',
                'reservation.segments' => fn ($q) => $q->orderBy('leg_order'),
                'reservation.segments.departure.line:id,uuid,code,name',
                'reservation.segments.seat:id,uuid,code',
                'reservation.segments.originStop:id,uuid,name',
                'reservation.segments.destinationStop:id,uuid,name',
            ])
            ->orderByDesc('issued_at');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(short_code) LIKE ?', [$term])
                    ->orWhereHas('reservationPassenger', fn ($p) => $p->whereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhere('phone', 'like', $term));
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, TicketStatus::values(), true)) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate(20);

        return inertia('tickets', [
            'tickets' => $paginated->through(fn (Ticket $ticket): array => $this->serializeTicket($ticket)),
            'statuses' => TicketStatus::options(),
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeTicket(Ticket $ticket): array
    {
        // reservation_id and reservation_passenger_id are NOT NULL on
        // tickets, so these two relations are always present. `$segment`
        // stays genuinely nullable (firstWhere can miss), so it keeps a
        // single nullsafe at the point it's actually used, with direct
        // access after that for its own NOT NULL relations (seat, stops,
        // departure).
        $passenger = $ticket->reservationPassenger;
        $reservation = $ticket->reservation;
        $segment = $reservation->segments->firstWhere('reservation_passenger_id', $passenger->id);
        $latestPayment = $reservation->payments->first();
        $departureStatus = $segment !== null ? $segment->departure->status : DepartureStatus::Scheduled;
        $journeyStatus = $segment !== null
            ? $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus)
            : null;

        return [
            'segmentUuid' => $segment?->uuid,
            'passenger' => [
                'uuid' => $passenger->uuid,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
            ],
            'seatCode' => $segment?->seat->code,
            'originStop' => $segment?->originStop->name,
            'destinationStop' => $segment?->destinationStop->name,
            'reservation' => [
                'uuid' => $reservation->uuid,
                'isGroupPurchase' => $reservation->relationLoaded('passengers')
                    ? $reservation->passengers->count() > 1
                    : null,
            ],
            'journeyStatus' => $journeyStatus === null ? null : [
                'value' => $journeyStatus->value,
                'label' => $journeyStatus->translatedLabel(),
            ],
            'ticket' => [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'status' => $ticket->status->value,
                'statusLabel' => $ticket->status->translatedLabel(),
                'issuedAt' => $ticket->issued_at?->toIso8601String(),
            ],
            'departure' => $segment === null ? null : [
                'uuid' => $segment->departure->uuid,
                'lineCode' => $segment->departure->line->code,
                'departureDate' => $segment->departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $segment->departure->departure_time, 0, 5),
            ],
        ];
    }
}
