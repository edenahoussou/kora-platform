<?php

namespace App\Modules\Boarding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Boarding\Actions\CloseNoShowsForDepartureAction;
use App\Modules\Boarding\Actions\ProcessSeatReleaseAction;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Http\Requests\NoShowClosureRequest;
use App\Modules\Boarding\Http\Requests\SeatReleaseRequest;
use App\Modules\Boarding\Http\Resources\SeatReleaseEventResource;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Departures\Data\DepartureStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group OPS Mobile — Seat releases
 *
 * Descente anticipée et clôture no-show : libère les portions de siège
 * non parcourues, le serveur restant seul maître de la disponibilité et
 * de la revente.
 */
class OpsSeatReleaseController extends Controller
{
    /**
     * Enregistre une descente anticipée à partir du billet, du départ et
     * de l'arrêt réel.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @bodyParam departure_uuid string required UUID public du départ (la jambe en cours). Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @bodyParam ticket_uuid string required UUID public du billet. Example: 3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41
     * @bodyParam actual_stop_uuid string required UUID public de l'arrêt où le passager est réellement descendu. Example: 5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21
     * @bodyParam operation_uuid string required UUID généré par le terminal, unique par tentative — les retries sont sans effet secondaire. Example: 6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99
     * @bodyParam local_recorded_at string required Horodatage local de l'appareil (ISO 8601). Example: 2026-10-01T09:12:00Z
     *
     * @response 200 scenario="Libération acceptée" {
     *   "data": {
     *     "uuid": "4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61",
     *     "status": "accepted",
     *     "ticketUuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41",
     *     "departureUuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "actualStopUuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
     *     "releasedSegments": [
     *       { "segment_uuid": "7c1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e33", "leg_order": 0, "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e" }
     *     ],
     *     "operationUuid": "6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99",
     *     "localRecordedAt": "2026-10-01T09:12:00.000Z",
     *     "serverRecordedAt": "2026-10-01T09:12:00.400Z",
     *     "code": null,
     *     "message": null,
     *     "recommendedAction": null
     *   }
     * }
     * @response 404 scenario="Billet inconnu" {
     *   "error": { "code": "ticket_unknown", "message": "Billet inconnu.", "recommendedAction": "Vérifiez le billet et réessayez." }
     * }
     * @response 409 scenario="Libération contradictoire (pas embarqué sur ce départ)" {
     *   "data": {
     *     "uuid": "6d1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e81",
     *     "status": "rejected",
     *     "code": "segment_not_boarded",
     *     "message": "Ce billet n’a pas été embarqué sur ce départ — impossible de signaler une descente.",
     *     "recommendedAction": "Embarquez d’abord le billet (scan) avant de signaler une descente anticipée."
     *   }
     * }
     * @response 409 scenario="Conflit entre deux terminaux (libéré hors ligne des deux côtés)" {
     *   "data": {
     *     "uuid": "8f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0ea5",
     *     "status": "conflict",
     *     "code": "seat_release_conflict",
     *     "message": "Ce trajet a déjà été libéré depuis un autre terminal.",
     *     "recommendedAction": "Vérifiez auprès de l’autre terminal avant de recommencer — la libération d’origine fait foi."
     *   }
     * }
     * @response 422 scenario="Arrêt non compatible" {
     *   "data": {
     *     "uuid": "7e1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e91",
     *     "status": "rejected",
     *     "code": "stop_not_compatible",
     *     "message": "Cet arrêt n’est pas compatible avec la jambe et la destination initiale du billet.",
     *     "recommendedAction": "Sélectionnez un arrêt situé entre l’embarquement et la destination du billet."
     *   }
     * }
     */
    public function store(SeatReleaseRequest $request, ProcessSeatReleaseAction $processRelease): JsonResponse
    {
        $departure = $request->departure();

        Gate::authorize('create', SeatReleaseEvent::class);
        Gate::authorize('releaseSeat', $departure);

        $terminal = $request->attributes->get('terminal');

        $event = $processRelease->execute(
            $departure,
            $terminal,
            $request->user(),
            $request->ticketUuid(),
            $request->actualStop(),
            $request->operationUuid(),
            $request->localRecordedAt(),
        );

        return response()->json(
            ['data' => new SeatReleaseEventResource($event)],
            $this->httpStatusFor($event),
        );
    }

    /**
     * Clôture no-show d'un départ : chaque billet encore "ticketed"
     * (jamais embarqué) est libéré. Autorisé uniquement une fois le
     * départ effectivement parti ou arrivé — les passagers peuvent
     * toujours se présenter tant que l'embarquement est en cours.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @bodyParam departure_uuid string required UUID public du départ à clôturer. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @bodyParam operation_uuid string required UUID généré par le terminal pour cette clôture — les retries sont sans effet secondaire (chaque billet no-show obtient un identifiant dérivé, déterministe). Example: 8a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0ea1
     * @bodyParam local_recorded_at string required Horodatage local de l'appareil (ISO 8601). Example: 2026-10-01T11:05:00Z
     *
     * @response 200 scenario="Clôture effectuée" {
     *   "data": [
     *     {
     *       "uuid": "9b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0eb1",
     *       "status": "accepted",
     *       "ticketUuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41",
     *       "departureUuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e"
     *     }
     *   ]
     * }
     * @response 422 scenario="Départ pas encore parti" {
     *   "error": { "code": "departure_not_closable", "message": "La clôture no-show n’est possible qu’une fois le départ parti ou arrivé." }
     * }
     */
    public function closeNoShows(NoShowClosureRequest $request, CloseNoShowsForDepartureAction $closeNoShows): JsonResponse
    {
        $departure = $request->departure();

        Gate::authorize('create', SeatReleaseEvent::class);
        Gate::authorize('releaseSeat', $departure);

        if (! in_array($departure->status, [DepartureStatus::Departed, DepartureStatus::Arrived], true)) {
            return response()->json([
                'error' => [
                    'code' => 'departure_not_closable',
                    'message' => 'La clôture no-show n’est possible qu’une fois le départ parti ou arrivé.',
                ],
            ], 422);
        }

        $terminal = $request->attributes->get('terminal');

        $events = $closeNoShows->execute(
            $departure,
            $terminal,
            $request->user(),
            $request->operationUuid(),
            $request->localRecordedAt(),
        );

        return response()->json(['data' => SeatReleaseEventResource::collection($events)]);
    }

    private function httpStatusFor(SeatReleaseEvent $event): int
    {
        return match ($event->status) {
            SeatReleaseEventStatus::Accepted => 200,
            SeatReleaseEventStatus::Conflict => 409,
            SeatReleaseEventStatus::Rejected => in_array($event->conflict_payload['code'] ?? null, ['segment_not_boarded', 'segment_already_released'], true) ? 409 : 422,
            SeatReleaseEventStatus::Pending => 202,
        };
    }
}
