<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\ManifestPassengerSerializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class ManifestController
{
    public function __construct(
        private readonly ManifestPassengerSerializer $manifestSerializer,
    ) {}

    public function show(Request $request, Departure $departure): Response
    {
        Gate::authorize('view', $departure);

        $departure->load([
            'line:id,uuid,code,name,type',
            'line.stops' => fn ($q) => $q->where('status', 'active')->orderBy('stop_order')->with('physicalStop:id,uuid,name,city'),
            'bus:id,uuid,registration_number,seat_count',
            'driver:id,uuid,name',
            'conductor:id,uuid,name',
        ]);

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

        $ticketsByPassengerId = $this->manifestSerializer->ticketsByPassengerId($segments);
        $canViewTickets = $request->user()?->can('tickets.view') ?? false;

        $lineStops = $departure->line->stops;
        $originStop = $lineStops->first();
        $destinationStop = $lineStops->last();

        return inertia('manifest/detail', [
            'departure' => [
                'uuid' => $departure->uuid,
                'lineCode' => $departure->line->code,
                'lineName' => $departure->line->name,
                'lineType' => $departure->line->type->value,
                'lineTypeLabel' => $departure->line->type->translatedLabel(),
                'originCity' => $originStop?->physicalStop->city ?? $originStop->city ?? $originStop->name,
                'destinationCity' => $destinationStop?->physicalStop->city ?? $destinationStop->city ?? $destinationStop->name,
                'busRegistration' => $departure->bus->registration_number,
                'seatCount' => $departure->bus->seat_count,
                'departureDate' => $departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
                'statusLabel' => $departure->status->translatedLabel(),
            ],
            'passengers' => $segments->map(fn (ReservationSegment $segment): array => $this->manifestSerializer->row($segment, $ticketsByPassengerId, $canViewTickets, $departure->status))->values(),
            'summary' => $this->manifestSerializer->summary($segments, $departure->status),
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

        $ticketsByPassengerId = $this->manifestSerializer->ticketsByPassengerId($reservation->segments);
        $canViewTickets = $request->user()?->can('tickets.view') ?? false;

        return inertia('manifest/reservation', [
            'reservation' => [
                'uuid' => $reservation->uuid,
                'status' => $reservation->status->value,
                'channel' => $reservation->channel?->value,
                'isGroupPurchase' => $reservation->passengers->count() > 1,
                'passengerCount' => $reservation->passengers->count(),
            ],
            'passengers' => $reservation->segments->map(fn (ReservationSegment $segment): array => $this->manifestSerializer->row($segment, $ticketsByPassengerId, $canViewTickets, $segment->departure->status, includeDeparture: true))->values(),
            'permissions' => [
                'canViewTickets' => $canViewTickets,
            ],
        ]);
    }
}
