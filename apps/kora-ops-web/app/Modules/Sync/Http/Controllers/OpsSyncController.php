<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Sync\Actions\CheckManifestVersionsAction;
use App\Modules\Sync\Actions\ProcessSyncBatchAction;
use App\Modules\Sync\Http\Requests\CheckManifestVersionsRequest;
use App\Modules\Sync\Http\Requests\SubmitSyncBatchRequest;
use App\Modules\Sync\Http\Resources\SyncBatchResource;
use Illuminate\Http\JsonResponse;

/**
 * @group OPS Mobile — Sync
 *
 * Contrat de synchronisation pour le convoyeur en réseau dégradé ou hors
 * ligne : vérification de version de manifeste et soumission par lot des
 * opérations enregistrées localement (embarquement, no-show, libération
 * de siège). Idempotent à deux niveaux (lot et opération) — un lot
 * rejoué, en tout ou partie, ne crée jamais de doublon.
 */
class OpsSyncController extends Controller
{
    /**
     * Vérifie, pour plusieurs départs à la fois, si le manifeste déjà en
     * cache sur le terminal est encore à jour. Le re-téléchargement
     * effectif se fait ensuite via l'endpoint manifeste existant
     * (GET /api/v1/ops/departures/{departure}/manifest).
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @bodyParam departures array required Liste des départs à vérifier. Example: [{"departure_uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","known_version":"3f9a1c2e8b7d4a10"}]
     *
     * @response 200 scenario="Un périmé, un à jour, un hors périmètre" {
     *   "data": [
     *     { "departureUuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e", "currentVersion": "a1b2c3d4e5f60718", "stale": true, "accessible": true },
     *     { "departureUuid": "7c1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e33", "currentVersion": "3f9a1c2e8b7d4a10", "stale": false, "accessible": true },
     *     { "departureUuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21", "currentVersion": null, "stale": false, "accessible": false }
     *   ]
     * }
     */
    public function manifestVersions(CheckManifestVersionsRequest $request, CheckManifestVersionsAction $checkVersions): JsonResponse
    {
        return response()->json([
            'data' => $checkVersions->execute($request->user(), $request->departures()),
        ]);
    }

    /**
     * Soumet un lot d'opérations enregistrées localement. Chaque
     * opération reçoit un résultat indépendant (accepté, pending, conflit
     * ou rejeté) — un lot partiellement réussi n'échoue jamais dans son
     * ensemble. Les libérations de segment (seat_release, boarding.
     * no_show) sont traitées avant les embarquements.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @bodyParam batch_uuid string required UUID généré par le terminal pour ce lot — un rejeu du même batch_uuid renvoie le résultat déjà calculé, sans retraiter. Example: 1c9c3b2a-8f1a-4b3e-9a1a-2f4b6c8d0f01
     * @bodyParam operations array required Liste des opérations du lot (max 200). `payload` accepte librement tout champ additionnel utile à l'audit — notamment `manifest_version`, la version du manifeste que le terminal avait en cache au moment de l'action (stockée telle quelle, jamais interprétée pour bloquer le traitement). Example: [{"operation_uuid":"6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99","operation_type":"boarding.scan","local_recorded_at":"2026-10-01T07:58:00Z","payload":{"departure_uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","short_code":"AB12CD","manifest_version":"3f9a1c2e8b7d4a10"}}]
     *
     * @response 200 scenario="Lot partiellement accepté" {
     *   "data": {
     *     "uuid": "1c9c3b2a-8f1a-4b3e-9a1a-2f4b6c8d0f01",
     *     "status": "completed",
     *     "submittedAt": "2026-10-01T08:00:00.000Z",
     *     "processedAt": "2026-10-01T08:00:00.400Z",
     *     "summary": { "accepted": 1, "pending": 0, "conflict": 0, "rejected": 1 },
     *     "operations": [
     *       { "operationUuid": "6f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e99", "operationType": "boarding.scan", "status": "accepted", "result": { "status": "accepted", "code": null, "message": null, "recommendedAction": null, "entityType": "boarding_event", "entityUuid": "4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61" } },
     *       { "operationUuid": "7f1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e77", "operationType": "seat_release", "status": "rejected", "result": { "status": "rejected", "code": "ticket_cancelled", "message": "Ce billet a été annulé.", "recommendedAction": "Aucune action — le billet est annulé.", "entityType": "seat_release_event", "entityUuid": "8a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e88" } }
     *     ]
     *   }
     * }
     */
    public function submitBatch(SubmitSyncBatchRequest $request, ProcessSyncBatchAction $processBatch): JsonResponse
    {
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $batch = $processBatch->execute($terminal, $request->user(), $request->batchUuid(), $request->operations());

        return response()->json(['data' => new SyncBatchResource($batch)]);
    }
}
