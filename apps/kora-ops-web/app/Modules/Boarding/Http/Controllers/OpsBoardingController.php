<?php

namespace App\Modules\Boarding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Boarding\Actions\ProcessBoardingScanAction;
use App\Modules\Boarding\Actions\ReverseBoardingEventAction;
use App\Modules\Boarding\Data\BoardingEventStatus;
use App\Modules\Boarding\Http\Requests\BoardingScanRequest;
use App\Modules\Boarding\Http\Requests\ReverseBoardingEventRequest;
use App\Modules\Boarding\Http\Resources\BoardingEventResource;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * @group OPS Mobile — Boarding
 *
 * Contrôle serveur d'un billet (QR signé ou code court) pour un départ et
 * une jambe précis, avec idempotence par operation_uuid et détection des
 * conflits entre terminaux.
 */
class OpsBoardingController extends Controller
{
    /**
     * Valide un scan d'embarquement.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @bodyParam departure_uuid string required UUID public du départ scanné. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @bodyParam qr_payload string Contenu signé du QR (requis si short_code absent). Example: eyJ2IjoyfQ.c2ln
     * @bodyParam short_code string Code court saisi manuellement (requis si qr_payload absent). Example: AB12CD
     * @bodyParam operation_uuid string required UUID généré par le terminal, unique par tentative — les retries avec le même operation_uuid sont sans effet secondaire. Example: 6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99
     * @bodyParam local_recorded_at string required Horodatage local de l'appareil au moment du scan (ISO 8601). Example: 2026-10-01T07:58:03Z
     *
     * @response 200 scenario="Embarquement accepté" {
     *   "data": {
     *     "uuid": "4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61",
     *     "status": "accepted",
     *     "ticketUuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41",
     *     "departureUuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "operationUuid": "6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99",
     *     "localRecordedAt": "2026-10-01T07:58:03.000Z",
     *     "serverRecordedAt": "2026-10-01T07:58:03.400Z",
     *     "code": null,
     *     "message": null
     *   }
     * }
     * @response 404 scenario="Billet inconnu" {
     *   "error": { "code": "qr_unknown", "message": "Billet inconnu ou signature invalide." }
     * }
     * @response 409 scenario="Conflit entre terminaux" {
     *   "data": {
     *     "uuid": "5c1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e71",
     *     "status": "conflict",
     *     "code": "boarding_conflict",
     *     "message": "Ce billet a déjà été embarqué sur un autre terminal."
     *   }
     * }
     * @response 422 scenario="Billet annulé" {
     *   "data": {
     *     "uuid": "6d1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e81",
     *     "status": "rejected",
     *     "code": "ticket_cancelled",
     *     "message": "Ce billet a été annulé."
     *   }
     * }
     */
    public function store(BoardingScanRequest $request, ProcessBoardingScanAction $processScan): JsonResponse
    {
        $departure = $request->departure();

        Gate::authorize('create', BoardingEvent::class);
        Gate::authorize('scanBoarding', $departure);

        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $event = $processScan->execute(
            $departure,
            $terminal,
            $request->operationUuid(),
            $request->localRecordedAt(),
            $request->qrPayload(),
            $request->shortCode(),
        );

        return response()->json(
            ['data' => new BoardingEventResource($event)],
            $this->httpStatusFor($event),
        );
    }

    /**
     * Annulation exceptionnelle d'un embarquement déjà accepté. Permission
     * dédiée (`boarding.reverse`), motif obligatoire, audit immuable
     * (l'événement d'origine n'est jamais modifié ni supprimé).
     *
     * @authenticated
     *
     * @urlParam boardingEvent string required UUID public de l'événement d'embarquement à annuler. Example: 4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61
     *
     * @bodyParam reason string required Motif de l'annulation exceptionnelle (5 à 500 caractères). Example: Scan erroné, passager non présent à bord.
     *
     * @response 200 scenario="Embarquement annulé" {
     *   "data": {
     *     "uuid": "4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61",
     *     "status": "accepted",
     *     "ticketUuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41",
     *     "departureUuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e"
     *   }
     * }
     */
    public function reverse(
        ReverseBoardingEventRequest $request,
        BoardingEvent $boardingEvent,
        ReverseBoardingEventAction $reverseEvent,
    ): JsonResponse {
        if ($boardingEvent->status !== BoardingEventStatus::Accepted) {
            return response()->json([
                'error' => [
                    'code' => 'boarding_event_not_reversible',
                    'message' => 'Seul un embarquement accepté peut être annulé.',
                ],
            ], 422);
        }

        $event = $reverseEvent->execute($boardingEvent, $request->user(), $request->reason());

        return response()->json(['data' => new BoardingEventResource($event)]);
    }

    /**
     * PDF de confirmation d'embarquement pour l'app OPS Mobile en mode
     * téléphone (pas de terminal P501, donc pas d'imprimante thermique —
     * voir ticket "Supporter le scan par caméra téléphone en alternative
     * au P501"). Réutilise le même document/action que le billet
     * passager (`TicketController::downloadPdf`, `GenerateTicketPdfAction`)
     * pour un rendu identique, mais SANS jamais exposer le montant payé
     * ni le numéro MECeF au convoyeur — voir `hideAmount` sur l'action.
     *
     * Autorisation par affectation au départ (même permission que le
     * scan, `scanBoarding`), pas par propriété de la réservation : le
     * convoyeur n'est ni le créateur ni le payeur du billet.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @urlParam departure string required UUID public du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @urlParam ticket string required UUID public du billet. Example: 3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41
     *
     * @response 200 scenario="PDF de confirmation" <<binary>>
     * @response 422 scenario="Billet hors périmètre de ce départ" {
     *   "error": { "code": "ticket_not_for_departure", "message": "Ce billet ne correspond pas à ce départ." }
     * }
     * @response 500 scenario="Génération échouée" {
     *   "error": { "code": "ticket_pdf_generation_failed", "message": "Le PDF de confirmation n'a pas pu être généré." }
     * }
     */
    public function pdf(
        Departure $departure,
        Ticket $ticket,
        GenerateTicketPdfAction $generatePdf,
    ): Response|JsonResponse {
        Gate::authorize('scanBoarding', $departure);

        $belongsToDeparture = ReservationSegment::query()
            ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
            ->where('departure_id', $departure->id)
            ->exists();

        if (! $belongsToDeparture) {
            return response()->json([
                'error' => [
                    'code' => 'ticket_not_for_departure',
                    'message' => 'Ce billet ne correspond pas à ce départ.',
                ],
            ], 422);
        }

        try {
            $pdf = $generatePdf->execute($ticket, hideAmount: true);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => [
                    'code' => 'ticket_pdf_generation_failed',
                    'message' => "Le PDF de confirmation n'a pas pu être généré.",
                ],
            ], 500);
        }

        return response($pdf['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function httpStatusFor(BoardingEvent $event): int
    {
        return match ($event->status) {
            BoardingEventStatus::Accepted => 200,
            BoardingEventStatus::Conflict => 409,
            BoardingEventStatus::Rejected => (($event->conflict_payload['code'] ?? null) === 'ticket_already_boarded') ? 409 : 422,
            BoardingEventStatus::Pending => 202,
        };
    }
}
