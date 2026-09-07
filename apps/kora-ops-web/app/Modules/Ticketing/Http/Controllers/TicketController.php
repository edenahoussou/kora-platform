<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Http\Resources\TicketResource;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Tickets
 *
 * Ticket retrieval endpoints for issued boarding passes.
 */
class TicketController extends Controller
{
    /**
     * List all tickets for a reservation.
     *
     * @authenticated
     *
     * @response 200 scenario="Ticket list" {
     *   "data": [
     *     {
     *       "uuid": "t1234567-89ab-cdef-0123-456789abcdef",
     *       "short_code": "KORA-AB12CD",
     *       "qr_code": "data:image/png;base64,iVBORw0KGgo...",
     *       "status": "issued",
     *       "issued_at": "2026-08-20T08:10:00+00:00",
     *       "reservation_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *       "passenger": {
     *         "uuid": "p1234567-89ab-cdef-0123-456789abcdef",
     *         "first_name": "Aïcha",
     *         "last_name": "Hounkpè"
     *       },
     *       "segments": [
     *         {
     *           "leg_order": 0,
     *           "departure_uuid": "dep12345-6789-abcd-ef01-23456789abcd",
     *           "origin_stop_uuid": "stp12345-6789-abcd-ef01-23456789abcd",
     *           "destination_stop_uuid": "stp23456-6789-abcd-ef01-23456789abcd",
     *           "seat_uuid": "seat123-4567-89ab-cdef-0123456789ab",
     *           "seat_code": "12A",
     *           "status": "active"
     *         }
     *       ]
     *     }
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "Vous n'avez pas accès à ce billet."
     * }
     */
    public function index(Reservation $reservation, Request $request): JsonResponse
    {
        $this->ensureOwner($reservation, $request);

        $tickets = $reservation->tickets()
            ->with(['reservation', 'reservationPassenger.segments.departure', 'reservationPassenger.segments.seat', 'reservationPassenger.segments.originStop', 'reservationPassenger.segments.destinationStop'])
            ->orderBy('id')
            ->get();

        return TicketResource::collection($tickets)->response()->setStatusCode(200);
    }

    /**
     * Retrieve a single ticket by UUID.
     *
     * @authenticated
     *
     * @response 200 scenario="Ticket details" {
     *   "data": {
     *     "uuid": "t1234567-89ab-cdef-0123-456789abcdef",
     *     "short_code": "KORA-AB12CD",
     *     "qr_code": "data:image/png;base64,iVBORw0KGgo...",
     *     "status": "issued",
     *     "issued_at": "2026-08-20T08:10:00+00:00",
     *     "reservation_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "passenger": {
     *       "uuid": "p1234567-89ab-cdef-0123-456789abcdef",
     *       "first_name": "Aïcha",
     *       "last_name": "Hounkpè"
     *     },
     *     "segments": [
     *       {
     *         "leg_order": 0,
     *         "departure_uuid": "dep12345-6789-abcd-ef01-23456789abcd",
     *         "origin_stop_uuid": "stp12345-6789-abcd-ef01-23456789abcd",
     *         "destination_stop_uuid": "stp23456-6789-abcd-ef01-23456789abcd",
     *         "seat_uuid": "seat123-4567-89ab-cdef-0123456789ab",
     *         "seat_code": "12A",
     *         "status": "active"
     *       }
     *     ]
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "Vous n'avez pas accès à ce billet."
     * }
     * @response 404 scenario="Ticket not found" {
     *   "message": "No query results for model [App\\Modules\\Ticketing\\Models\\Ticket]."
     * }
     */
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
