<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Departures\Actions\ProgressDepartureAction;
use App\Modules\Departures\Actions\RecordDepartureDelayAction;
use App\Modules\Departures\Exceptions\DepartureProgressException;
use App\Modules\Departures\Http\Requests\ProgressDepartureRequest;
use App\Modules\Departures\Http\Requests\RecordOpsDelayRequest;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group OPS Mobile — Progression du départ
 *
 * Le convoyeur fait avancer SON départ : embarquement commencé, bus parti,
 * arrivé, et enregistre un retard. C'est le même statut que sur OPS Web
 * (`departures.status`) : un « bus parti » déclaré ici est visible côté web,
 * bloque la vente du départ et débloque la clôture des no-show.
 *
 * Réservé à l'équipage affecté (permission `departures.progress`). Annuler
 * ou modifier un départ reste réservé à OPS Web.
 */
class OpsDepartureProgressController extends Controller
{
    /**
     * Change le statut du départ (embarquement → parti → arrivé).
     *
     * Transitions : programmé → embarquement → parti → arrivé. Idempotent :
     * redemander le statut déjà atteint renvoie 200 sans effet (rejeu hors
     * ligne). `occurred_at` est l'heure locale du terminal ; à défaut,
     * l'heure de réception.
     *
     * @authenticated
     *
     * @urlParam departure string required UUID du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @bodyParam status string required boarding, departed ou arrived. Example: departed
     * @bodyParam occurred_at string Heure locale du terminal (ISO 8601). Example: 2026-09-22T08:07:00Z
     *
     * @response 200 scenario="Statut mis à jour" {
     *   "data": { "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e", "status": "departed", "boarding_started_at": "2026-09-22T07:40:00.000Z", "departed_at": "2026-09-22T08:07:00.000Z", "arrived_at": null, "delay_minutes": 0, "delay_reason": null, "delay_recorded_at": null }
     * }
     * @response 403 scenario="Départ non affecté" {
     *   "error": { "code": "departure_not_assigned", "message": "Ce départ ne vous est pas affecté." }
     * }
     * @response 422 scenario="Transition impossible" {
     *   "error": { "code": "invalid_transition", "message": "Transition impossible : Programmé → En route." }
     * }
     */
    public function updateStatus(ProgressDepartureRequest $request, Departure $departure, ProgressDepartureAction $progress): JsonResponse
    {
        $user = $this->crewMember($request, $departure);

        $updated = $progress->execute($user, $departure, $request->target(), $request->occurredAt());

        return response()->json(['data' => $this->payload($updated)]);
    }

    /**
     * Enregistre un retard (départ programmé ou en embarquement).
     *
     * @authenticated
     *
     * @urlParam departure string required UUID du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @bodyParam delay_minutes integer required Retard en minutes (0 pour l'annuler). Example: 25
     * @bodyParam delay_reason string required Motif. Example: Attente d'un car de correspondance
     *
     * @response 422 scenario="Retard refusé" {
     *   "error": { "code": "invalid_delay", "message": "Le retard ne peut être enregistré que pour un départ programmé ou en embarquement." }
     * }
     */
    public function recordDelay(RecordOpsDelayRequest $request, Departure $departure, RecordDepartureDelayAction $recordDelay): JsonResponse
    {
        $user = $this->crewMember($request, $departure);

        try {
            $updated = $recordDelay->handle($departure, $user, $request->delayMinutes(), $request->delayReason());
        } catch (ValidationException $exception) {
            throw DepartureProgressException::invalidDelay((string) collect($exception->errors())->flatten()->first());
        }

        return response()->json(['data' => $this->payload($updated)]);
    }

    /**
     * Permission `departures.progress` (codes de session OPS sinon) puis
     * affectation au départ.
     */
    private function crewMember(Request $request, Departure $departure): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('departures.progress')) {
            throw new OpsSessionException(OpsSessionErrorCode::PermissionDenied);
        }

        if (! app(CrewDepartureScope::class)->canProgress($user, $departure)) {
            throw DepartureProgressException::notAssigned();
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Departure $departure): array
    {
        return [
            'uuid' => $departure->uuid,
            'status' => $departure->status->value,
            'boarding_started_at' => $departure->boarding_started_at?->toISOString(),
            'departed_at' => $departure->departed_at?->toISOString(),
            'arrived_at' => $departure->arrived_at?->toISOString(),
            'delay_minutes' => (int) $departure->delay_minutes,
            'delay_reason' => $departure->delay_reason,
            'delay_recorded_at' => $departure->delay_recorded_at?->toISOString(),
        ];
    }
}
