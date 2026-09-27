<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Tracking\Actions\CloseTrackingSessionAction;
use App\Modules\Tracking\Actions\IngestTrackingPositionsBatchAction;
use App\Modules\Tracking\Actions\OpenTrackingSessionAction;
use App\Modules\Tracking\Data\TrackingSessionCloseReason;
use App\Modules\Tracking\Exceptions\TrackingException;
use App\Modules\Tracking\Http\Requests\IngestTrackingPositionsRequest;
use App\Modules\Tracking\Models\TrackingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Mobile — Suivi de la flotte
 *
 * Ingestion par lots des positions GPS du terminal du convoyeur (ticket
 * MEZ-100). Idempotent : `position_uuid` est fourni par le terminal, un
 * rejeu (coupure réseau) ne crée jamais de doublon. Chaque terminal ne peut
 * publier que pour sa propre session, son convoyeur et son départ autorisés
 * — la garde `ops.session` lie déjà le jeton au terminal ; ce contrôleur
 * vérifie en plus que le départ est bien affecté à ce convoyeur.
 *
 * Erreurs : `{ error: { code, message } }` — `departure_not_assigned` (403),
 * `departure_ended` (409, départ déjà arrivé/annulé), `too_many_positions` (422),
 * plus les codes de session OPS.
 */
class OpsTrackingController extends Controller
{
    /**
     * Publie un lot de positions.
     *
     * @authenticated
     *
     * @bodyParam departure_uuid string required Départ concerné (doit vous être affecté). Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @bodyParam positions array required 1 à 200 positions. Example: [{"position_uuid":"6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11","sequence":1,"recorded_at":"2026-09-22T09:14:00Z","latitude":6.3654,"longitude":2.4183,"accuracy_meters":8.5,"speed_kmh":62.4,"heading_degrees":134.2,"source":"gps","status":"fix"}]
     *
     * @response 200 scenario="Lot accepté" {
     *   "data": { "session_uuid": "...", "accepted": 1, "duplicate": 0, "rejected": 0, "rejected_details": [] }
     * }
     * @response 403 scenario="Départ inconnu ou non affecté" {
     *   "error": { "code": "departure_not_assigned", "message": "Ce départ ne vous est pas affecté." }
     * }
     */
    public function storeBatch(
        IngestTrackingPositionsRequest $request,
        IngestTrackingPositionsBatchAction $ingest,
        OpenTrackingSessionAction $open,
        CloseTrackingSessionAction $close,
    ): JsonResponse {
        $agent = $this->reporter($request);

        $departure = Departure::query()->where('uuid', $request->departureUuid())->first();

        if ($departure === null || ! app(CrewDepartureScope::class)->canReportTracking($agent, $departure)) {
            throw TrackingException::notAssigned();
        }

        if (in_array($departure->status, [DepartureStatus::Arrived, DepartureStatus::Cancelled], true)) {
            throw TrackingException::departureEnded();
        }

        $terminal = $request->attributes->get('terminal');

        if (! $terminal instanceof TerminalP501) {
            throw TrackingException::notAssigned();
        }

        $session = $this->resolveSession($departure, $terminal, $open, $close);

        $result = $ingest->execute($session, $terminal, $agent, $request->positions());

        return response()->json([
            'data' => [
                'session_uuid' => $session->uuid,
                ...$result,
            ],
        ]);
    }

    /**
     * Résout la session ouverte de ce départ pour CE terminal. Si une
     * session ouverte existe pour un AUTRE terminal (convoyeur qui a changé
     * d'appareil en route), elle est close (`terminal_reassigned`) et une
     * nouvelle est ouverte — jamais deux sessions ouvertes pour un même
     * départ.
     */
    private function resolveSession(
        Departure $departure,
        TerminalP501 $terminal,
        OpenTrackingSessionAction $open,
        CloseTrackingSessionAction $close,
    ): TrackingSession {
        $existing = TrackingSession::query()->where('departure_id', $departure->id)->open()->first();

        if ($existing !== null && $existing->terminal_p501_id !== $terminal->id) {
            $close->execute($existing, TrackingSessionCloseReason::TerminalReassigned);
            $existing = null;
        }

        $session = $existing ?? $open->execute($departure, $terminal);

        if ($session === null) {
            throw TrackingException::notAssigned();
        }

        return $session;
    }

    /**
     * Utilisateur authentifié, avec la permission `tracking.report` (les
     * erreurs suivent les codes de session OPS : permission_denied…).
     */
    private function reporter(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('tracking.report')) {
            throw new OpsSessionException(OpsSessionErrorCode::PermissionDenied);
        }

        return $user;
    }
}
