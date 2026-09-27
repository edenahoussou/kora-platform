<?php

namespace App\Modules\FleetLite\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\FleetLite\Actions\ReportIncidentAction;
use App\Modules\FleetLite\Exceptions\IncidentException;
use App\Modules\FleetLite\Http\Requests\ListIncidentsRequest;
use App\Modules\FleetLite\Http\Requests\StoreIncidentRequest;
use App\Modules\FleetLite\Http\Resources\OpsIncidentResource;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Mobile — Incidents
 *
 * Signalement d'un incident par le convoyeur (panne, retard, accident,
 * sécurité, autre). Un incident déclenche une alerte : notification à
 * l'équipage du départ et aux directeurs des opérations de l'agence, e-mail
 * aux directeurs (destinataires) et super-administrateurs (copie).
 *
 * Idempotent : le mobile génère `incident_uuid` ; un rejeu (coupure réseau)
 * renvoie l'incident existant (200) sans doublon ni nouvelle alerte.
 * Chaque convoyeur ne voit que SES incidents. Aucun identifiant numérique,
 * aucune donnée financière.
 *
 * Erreurs : `{ error: { code, message } }` — `departure_not_assigned` (403),
 * `incident_uuid_conflict` (409), plus les codes de session OPS.
 */
class OpsIncidentController extends Controller
{
    /**
     * Signale un incident.
     *
     * @authenticated
     *
     * @bodyParam incident_uuid string required UUID généré par le mobile (clé d'idempotence). Example: 6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11
     * @bodyParam type string required breakdown, delay, accident, security ou other. Example: breakdown
     * @bodyParam description string required 5 à 1000 caractères. Example: Crevaison au niveau de Ouidah, changement de roue en cours.
     * @bodyParam departure_uuid string required Départ concerné (doit vous être affecté). Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @bodyParam latitude number Position GPS (avec longitude). Example: 6.3654
     * @bodyParam longitude number Position GPS (avec latitude). Example: 2.4183
     * @bodyParam occurred_at string Heure locale du terminal (ISO 8601). Example: 2026-09-22T09:14:00Z
     *
     * @response 201 scenario="Incident créé" {
     *   "data": { "uuid": "6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11", "type": "breakdown", "status": "open", "description": "Crevaison au niveau de Ouidah, changement de roue en cours.", "latitude": 6.3654, "longitude": 2.4183, "occurred_at": "2026-09-22T09:14:00.000Z", "created_at": "2026-09-22T09:14:05.000Z", "departure": { "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e", "line_code": "COT-LFW", "date": "2026-09-22", "time": "08:00" } }
     * }
     * @response 200 scenario="Rejeu du même incident (idempotent)" {
     *   "data": { "uuid": "6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11", "type": "breakdown", "status": "open" }
     * }
     * @response 403 scenario="Départ inconnu ou non affecté" {
     *   "error": { "code": "departure_not_assigned", "message": "Ce départ ne vous est pas affecté." }
     * }
     * @response 409 scenario="Identifiant déjà utilisé par un autre utilisateur" {
     *   "error": { "code": "incident_uuid_conflict", "message": "Cet identifiant d’incident est déjà utilisé." }
     * }
     */
    public function store(StoreIncidentRequest $request, ReportIncidentAction $report): JsonResponse
    {
        $user = $this->reporter($request);

        $departure = Departure::query()->where('uuid', $request->departureUuid())->first();

        if ($departure === null || ! app(CrewDepartureScope::class)->canReportIncident($user, $departure)) {
            throw IncidentException::notAssigned();
        }

        $terminal = $request->attributes->get('terminal');

        [$incident, $created] = $report->execute(
            $user,
            $departure,
            $terminal instanceof TerminalP501 ? $terminal : null,
            $request->incidentUuid(),
            $request->incidentType(),
            $request->string('description')->toString(),
            $request->latitude(),
            $request->longitude(),
            $request->occurredAt(),
        );

        return response()->json([
            'data' => (new OpsIncidentResource($incident->load('departure.line')))->resolve($request),
        ], $created ? 201 : 200);
    }

    /**
     * Liste MES incidents, les plus récents d'abord.
     *
     * @authenticated
     *
     * @queryParam status string open, in_progress, resolved ou cancelled. Example: open
     * @queryParam departure_uuid string Filtrer par départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     * @queryParam per_page integer 1 à 100 (30 par défaut). Example: 30
     *
     * @response 200 scenario="Incidents du convoyeur" {"data":[{"uuid":"6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11","type":"breakdown","status":"open","description":"Crevaison au niveau de Ouidah.","latitude":6.3654,"longitude":2.4183,"occurred_at":"2026-09-22T09:14:00.000Z","created_at":"2026-09-22T09:14:05.000Z","departure":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","line_code":"COT-LFW","date":"2026-09-22","time":"08:00"}}],"meta":{"current_page":1,"per_page":30,"total":1,"last_page":1}}
     * @response 401 scenario="Session absente ou expirée" {"error":{"code":"session_expired","message":"Votre session a expiré.","action":"reauthenticate"}}
     */
    public function index(ListIncidentsRequest $request): JsonResponse
    {
        $user = $this->reporter($request);

        $query = Incident::query()
            ->where('created_by', $user->id)
            ->with('departure.line')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('departure_uuid')) {
            $query->whereHas('departure', fn ($departure) => $departure->where('uuid', $request->string('departure_uuid')->toString()));
        }

        $page = $query->paginate($request->perPage());

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (Incident $incident): array => (new OpsIncidentResource($incident))->resolve($request))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Détail d'un de MES incidents (404 pour celui d'un autre).
     *
     * @authenticated
     *
     * @urlParam incident string required UUID de l'incident. Example: 6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11
     *
     * @response 200 scenario="Incident du convoyeur" {"data":{"uuid":"6f1b0c9e-3f6a-4c0e-9a55-2d7b7d0a1c11","type":"breakdown","status":"open","description":"Crevaison au niveau de Ouidah.","latitude":6.3654,"longitude":2.4183,"occurred_at":"2026-09-22T09:14:00.000Z","created_at":"2026-09-22T09:14:05.000Z","departure":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","line_code":"COT-LFW","date":"2026-09-22","time":"08:00"}}}
     * @response 404 scenario="Incident absent de ce compte" {"message":"No query results for model [App\\Modules\\FleetLite\\Models\\Incident]."}
     */
    public function show(Request $request, string $incident): JsonResponse
    {
        $user = $this->reporter($request);

        $found = Incident::query()
            ->where('uuid', $incident)
            ->where('created_by', $user->id)
            ->with('departure.line')
            ->firstOrFail();

        return response()->json(['data' => (new OpsIncidentResource($found))->resolve($request)]);
    }

    /**
     * Utilisateur authentifié, avec la permission `incidents.report` (les
     * erreurs suivent les codes de session OPS : permission_denied…).
     */
    private function reporter(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('incidents.report')) {
            throw new OpsSessionException(OpsSessionErrorCode::PermissionDenied);
        }

        return $user;
    }
}
