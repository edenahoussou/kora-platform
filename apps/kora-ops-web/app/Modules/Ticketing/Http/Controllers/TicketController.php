<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Http\Resources\TicketResource;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Reservation $reservation, Request $request): JsonResponse
    {
        $this->ensureOwner($reservation, $request);

        $tickets = $reservation->tickets()
            ->with(['reservation', 'reservationPassenger.segments.departure', 'reservationPassenger.segments.seat', 'reservationPassenger.segments.originStop', 'reservationPassenger.segments.destinationStop'])
            ->orderBy('id')
            ->get();

        return TicketResource::collection($tickets)->response()->setStatusCode(200);
    }

    public function show(Ticket $ticket, Request $request): JsonResponse
    {
        $ticket->loadMissing(['reservation', 'reservationPassenger.segments.departure', 'reservationPassenger.segments.seat', 'reservationPassenger.segments.originStop', 'reservationPassenger.segments.destinationStop']);
        $this->ensureOwner($ticket->reservation, $request);

        return (new TicketResource($ticket))->response()->setStatusCode(200);
    }

    private function ensureOwner(?Reservation $reservation, Request $request): void
    {
        if ($reservation?->created_by_user_id === null
            || ($reservation->created_by_user_id !== $request->user()->getKey() && $reservation->payer_user_id !== $request->user()->getKey())) {
            throw new AuthorizationException('Vous n’avez pas accès à ce billet.');
        }
    }
}
