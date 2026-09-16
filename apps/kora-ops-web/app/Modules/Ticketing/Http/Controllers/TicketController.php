<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Http\Resources\PassengerTicketResource;
use App\Modules\Ticketing\Http\Resources\TicketResource;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * @group Tickets
 *
 * Ticket retrieval endpoints for issued boarding passes.
 */
class TicketController extends Controller
{
    /**
     * List all tickets belonging to the authenticated passenger.
     *
     * Returns every issued ticket for which the authenticated user is either
     * the creator (`created_by_user_id`) or the payer (`payer_user_id`) of the
     * underlying reservation, across all statuses. The response is paginated
     * and uses the `PassengerTicketResource` contract, which maps backend
     * statuses to the app-facing vocabulary (confirmed / completed / cancelled).
     *
     * @authenticated
     *
     * @queryParam page int Page number (default 1). Example: 1
     *
     * @response 200 scenario="Passenger ticket list" {
     *   "data": [
     *     {
     *       "uuid": "t1234567-89ab-cdef-0123-456789abcdef",
     *       "short_code": "KE-20260804-78342",
     *       "qr_code": "v2.eyJpdiI6...",
     *       "status": "confirmed",
     *       "issued_at": "2026-08-20T08:10:00+00:00",
     *       "reservation_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *       "amount": 12500,
     *       "currency": "XOF",
     *       "passenger": {
     *         "uuid": "p1234567-89ab-cdef-0123-456789abcdef",
     *         "first_name": "Aïcha",
     *         "last_name": "Hounkpè"
     *       },
     *       "origin": {
     *         "city": "Cotonou",
     *         "city_code": "CTN",
     *         "stop_name": "Cotonou Dantokpa"
     *       },
     *       "destination": {
     *         "city": "Malanville",
     *         "city_code": "MLV",
     *         "stop_name": "Malanville Centre"
     *       },
     *       "departure": {
     *         "uuid": "dep12345-6789-abcd-ef01-23456789abcd",
     *         "departs_at": "2026-08-04T07:30:00+00:00",
     *         "line_name": "Cotonou-Malanville",
     *         "line_code": "L1"
     *       },
     *       "seat": {
     *         "uuid": "seat123-4567-89ab-cdef-0123456789ab",
     *         "code": "4A"
     *       }
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 5
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $tickets = Ticket::query()
            ->whereHas('reservation', function ($q) use ($userId): void {
                $q->where('created_by_user_id', $userId)
                    ->orWhere('payer_user_id', $userId);
            })
            ->with([
                'reservation',
                'reservationPassenger.segments.departure.line',
                'reservationPassenger.segments.originStop.physicalStop',
                'reservationPassenger.segments.destinationStop.physicalStop',
                'reservationPassenger.segments.seat',
            ])
            ->orderByDesc('issued_at')
            ->paginate(15);

        return PassengerTicketResource::collection($tickets)->response()->setStatusCode(200);
    }

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
     *           "leg_order": 1,
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
     *         "leg_order": 1,
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

    /**
     * Download the official PDF for an issued passenger ticket.
     *
     * The PDF is generated by the same action used by the web and WhatsApp
     * surfaces, so the document content remains identical across channels.
     *
     * @authenticated
     *
     * @urlParam ticket_uuid string required Public UUID of the ticket. Example: f1a2b3c4-d5e6-7890-abcd-ef1234567890
     *
     * @response 200 scenario="Ticket PDF" <<binary>>
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "Vous n'avez pas accès à ce billet."
     * }
     * @response 404 scenario="Ticket not found" {
     *   "message": "No query results for model [App\\Modules\\Ticketing\\Models\\Ticket]."
     * }
     * @response 422 scenario="PDF unavailable" {
     *   "error": {
     *     "code": "ticket_pdf_unavailable",
     *     "message": "Le PDF de ce billet n'est pas disponible."
     *   }
     * }
     * @response 500 scenario="PDF generation failed" {
     *   "error": {
     *     "code": "ticket_pdf_generation_failed",
     *     "message": "Le PDF du billet n'a pas pu être généré."
     *   }
     * }
     */
    public function downloadPdf(
        Ticket $ticket,
        Request $request,
        GenerateTicketPdfAction $generatePdf,
    ): Response|JsonResponse {
        $ticket->loadMissing('reservation');
        $this->ensureOwner($ticket->reservation, $request);

        if ($ticket->issued_at === null || ! in_array($ticket->status, [TicketStatus::Issued, TicketStatus::Used], true)) {
            return response()->json([
                'error' => [
                    'code' => 'ticket_pdf_unavailable',
                    'message' => "Le PDF de ce billet n'est pas disponible.",
                ],
            ], 422);
        }

        try {
            $pdf = $generatePdf->execute($ticket);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => [
                    'code' => 'ticket_pdf_generation_failed',
                    'message' => "Le PDF du billet n'a pas pu être généré.",
                ],
            ], 500);
        }

        return response($pdf['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureOwner(?Reservation $reservation, Request $request): void
    {
        if ($reservation?->created_by_user_id === null
            || ($reservation->created_by_user_id !== $request->user()->getKey() && $reservation->payer_user_id !== $request->user()->getKey())) {
            throw new AuthorizationException('Vous n’avez pas accès à ce billet.');
        }
    }
}
