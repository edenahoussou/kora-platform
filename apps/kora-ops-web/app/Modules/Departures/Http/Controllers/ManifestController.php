<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Modules\Booking\Actions\ResolvePassengerJourneyStatusAction;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class ManifestController
{
    public function __construct(
        private readonly ResolvePassengerJourneyStatusAction $resolveJourneyStatus,
    ) {}

    /**
     * Departure picker — same search/filter shape as the Departures screen,
     * but every row links straight to its manifest instead of the
     * departure's own edit/detail view.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $query = Departure::query()
            ->with(['line:id,uuid,code,name', 'bus:id,uuid,registration_number,seat_count'])
            ->withCount('reservationSegments')
            ->orderBy('departure_date', 'desc')
            ->orderBy('departure_time');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('line', fn ($line) => $line->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('bus', fn ($bus) => $bus->whereRaw('LOWER(registration_number) LIKE ?', [$term]));
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, DepartureStatus::values(), true)) {
            $query->where('status', $status);
        }

        $date = $request->string('date')->trim()->value();
        if ($date !== '') {
            $query->whereDate('departure_date', $date);
        }

        $paginated = $query->paginate(15);

        return inertia('manifest', [
            'departures' => $paginated->through(fn (Departure $departure): array => [
                'uuid' => $departure->uuid,
                'lineCode' => $departure->line->code,
                'lineName' => $departure->line->name,
                'busRegistration' => $departure->bus->registration_number,
                'departureDate' => $departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
                'passengerCount' => $departure->reservation_segments_count,
            ]),
            'statuses' => DepartureStatus::options(),
        ]);
    }

    public function show(Request $request, Departure $departure): Response
    {
        Gate::authorize('view', $departure);

        $departure->load(['line:id,uuid,code,name', 'bus:id,uuid,registration_number']);

        $segments = $departure->reservationSegments()
            ->with([
                'reservationPassenger',
                'reservation.payments' => fn ($q) => $q->orderByDesc('created_at'),
                'reservation.passengers:id,reservation_id',
                'seat:id,uuid,code',
                'originStop:id,uuid,name',
                'destinationStop:id,uuid,name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ticketsByPassengerId = $this->ticketsByPassengerId($segments);
        $canViewTickets = $request->user()?->can('tickets.view') ?? false;

        return inertia('manifest/detail', [
            'departure' => [
                'uuid' => $departure->uuid,
                'lineCode' => $departure->line->code,
                'lineName' => $departure->line->name,
                'busRegistration' => $departure->bus->registration_number,
                'departureDate' => $departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
            ],
            'passengers' => $segments->map(fn (ReservationSegment $segment): array => $this->serializeManifestRow($segment, $ticketsByPassengerId, $canViewTickets, $departure->status))->values(),
            'summary' => $this->buildSummary($segments, $departure->status),
            'permissions' => [
                'canViewTickets' => $canViewTickets,
            ],
        ]);
    }

    /**
     * All passengers of a single reservation (used for the "by reservation"
     * entry point, and to drill into a grouped purchase's siblings from the
     * manifest — a shared purchase must stay visibly tied to one
     * reservation while each passenger/ticket is still listed on its own).
     */
    public function reservation(Request $request, Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);

        $reservation->load([
            'passengers',
            'payments' => fn ($q) => $q->orderByDesc('created_at'),
            'segments.departure.line:id,uuid,code,name',
            'segments.seat:id,uuid,code',
            'segments.originStop:id,uuid,name',
            'segments.destinationStop:id,uuid,name',
        ]);

        $ticketsByPassengerId = $this->ticketsByPassengerId($reservation->segments);
        $canViewTickets = $request->user()?->can('tickets.view') ?? false;

        return inertia('manifest/reservation', [
            'reservation' => [
                'uuid' => $reservation->uuid,
                'status' => $reservation->status->value,
                'channel' => $reservation->channel?->value,
                'isGroupPurchase' => $reservation->passengers->count() > 1,
                'passengerCount' => $reservation->passengers->count(),
            ],
            // `departure_id` is a required column on reservation_segments,
            // so the relation is never null here — no nullsafe/fallback
            // needed (PHPStan flags it as dead code otherwise).
            'passengers' => $reservation->segments->map(fn (ReservationSegment $segment): array => $this->serializeManifestRow($segment, $ticketsByPassengerId, $canViewTickets, $segment->departure->status, includeDeparture: true))->values(),
            'permissions' => [
                'canViewTickets' => $canViewTickets,
            ],
        ]);
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return array<int, Ticket>
     */
    private function ticketsByPassengerId(Collection $segments): array
    {
        $passengerIds = $segments->pluck('reservation_passenger_id')->filter()->unique()->values();

        if ($passengerIds->isEmpty()) {
            return [];
        }

        return Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->get()
            ->keyBy('reservation_passenger_id')
            ->all();
    }

    /**
     * @param  array<int, Ticket>  $ticketsByPassengerId
     * @return array<string, mixed>
     */
    private function serializeManifestRow(ReservationSegment $segment, array $ticketsByPassengerId, bool $canViewTickets, DepartureStatus $departureStatus, bool $includeDeparture = false): array
    {
        $passenger = $segment->reservationPassenger;
        // reservation_id, seat_id, origin_stop_id, destination_stop_id and
        // departure_id are all NOT NULL on reservation_segments, so these
        // relations are never null — accessed directly, no nullsafe/fallback.
        $reservation = $segment->reservation;
        $ticket = $passenger !== null ? ($ticketsByPassengerId[$passenger->id] ?? null) : null;
        $latestPayment = $reservation->payments->first();

        return [
            'segmentUuid' => $segment->uuid,
            'passenger' => $passenger === null ? null : [
                'uuid' => $passenger->uuid,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
            ],
            'seatCode' => $segment->seat->code,
            'originStop' => $segment->originStop->name,
            'destinationStop' => $segment->destinationStop->name,
            'reservation' => [
                'uuid' => $reservation->uuid,
                'isGroupPurchase' => $reservation->relationLoaded('passengers')
                    ? $reservation->passengers->count() > 1
                    : null,
            ],
            'journeyStatus' => $this->journeyStatusPayload($segment, $latestPayment, $departureStatus),
            'ticket' => ! $canViewTickets ? null : ($ticket === null ? null : [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'status' => $ticket->status->value,
                'statusLabel' => $ticket->status->translatedLabel(),
                'issuedAt' => $ticket->issued_at?->toIso8601String(),
            ]),
            'departure' => ! $includeDeparture ? null : [
                'uuid' => $segment->departure->uuid,
                'lineCode' => $segment->departure->line->code,
                'departureDate' => $segment->departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $segment->departure->departure_time, 0, 5),
            ],
        ];
    }

    /** @return array{value: string, label: string} */
    private function journeyStatusPayload(ReservationSegment $segment, ?Payment $latestPayment, DepartureStatus $departureStatus): array
    {
        $status = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

        return ['value' => $status->value, 'label' => $status->translatedLabel()];
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return array<string, int>
     */
    private function buildSummary(Collection $segments, DepartureStatus $departureStatus): array
    {
        $counts = [
            'total' => $segments->count(),
            'boarded' => 0,
            'absent' => 0,
            'ticketIssued' => 0,
            'paymentPending' => 0,
            'cancelled' => 0,
        ];

        foreach ($segments as $segment) {
            $latestPayment = $segment->reservation->payments->first();
            $status = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

            match ($status->value) {
                'boarded' => $counts['boarded']++,
                'absent' => $counts['absent']++,
                'ticket_issued' => $counts['ticketIssued']++,
                'hold_pending', 'payment_pending' => $counts['paymentPending']++,
                'cancelled' => $counts['cancelled']++,
                default => null,
            };
        }

        return $counts;
    }
}
