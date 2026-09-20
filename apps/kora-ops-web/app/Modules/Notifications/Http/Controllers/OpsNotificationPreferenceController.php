<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Actions\UpdateOpsNotificationPreferencesAction;
use App\Modules\Notifications\Http\Requests\UpdateOpsNotificationPreferencesRequest;
use App\Modules\Notifications\Services\OpsNotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS — Notifications
 *
 * Préférences de notification : par type d'événement, canal in_app et canal
 * mail (e-mail d'alerte). Les alertes critiques (terminal_revoked,
 * scan_conflict) ne peuvent pas être désactivées.
 */
class OpsNotificationPreferenceController extends Controller
{
    /**
     * Préférences effectives de l'utilisateur, un élément par type.
     *
     * @authenticated
     *
     * @response 200 scenario="Préférences" {
     *   "data": [
     *     { "type": "scan_conflict", "title": "Conflit de scan", "severity": "critical", "disableable": false, "mail_supported": true, "in_app": true, "mail": true },
     *     { "type": "sync_completed", "title": "Synchronisation terminée", "severity": "info", "disableable": true, "mail_supported": false, "in_app": true, "mail": true }
     *   ]
     * }
     */
    public function show(Request $request, OpsNotificationPreferences $preferences): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $preferences->forUser($user)]);
    }

    /**
     * Met à jour des préférences. Tout ou rien : désactiver une alerte
     * critique est refusé (422) et n'enregistre rien.
     *
     * @authenticated
     *
     * @bodyParam preferences object[] required Préférences à modifier.
     * @bodyParam preferences[].type string required Type d'événement. Example: sync_completed
     * @bodyParam preferences[].in_app boolean Recevoir la notification. Example: false
     * @bodyParam preferences[].mail boolean Recevoir l'e-mail d'alerte (types d'alerte uniquement). Example: true
     *
     * @response 200 scenario="Préférences mises à jour" {
     *   "data": [ { "type": "sync_completed", "title": "Synchronisation terminée", "severity": "info", "disableable": true, "mail_supported": false, "in_app": false, "mail": true } ]
     * }
     * @response 422 scenario="Alerte critique" {
     *   "error": { "code": "notification_not_disableable", "message": "Les alertes critiques ne peuvent pas être désactivées.", "types": ["scan_conflict"] }
     * }
     */
    public function update(UpdateOpsNotificationPreferencesRequest $request, UpdateOpsNotificationPreferencesAction $update, OpsNotificationPreferences $preferences): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $update->execute($user, $request->preferences());

        return response()->json(['data' => $preferences->forUser($user)]);
    }
}
