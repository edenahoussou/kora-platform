<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Actions\MarkAllOpsNotificationsReadAction;
use App\Modules\Notifications\Actions\MarkOpsNotificationReadAction;
use App\Modules\Notifications\Http\Requests\ListOpsNotificationsRequest;
use App\Modules\Notifications\Http\Requests\MarkAllOpsNotificationsReadRequest;
use App\Modules\Notifications\Http\Resources\OpsNotificationResource;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Services\OpsNotificationQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS — Notifications
 *
 * Alertes opérationnelles boarding pour le convoyeur (OPS Mobile) et les
 * opérateurs (OPS Web) : même contrat, mêmes autorisations. Chaque
 * utilisateur ne voit que SES notifications ; aucune ne contient de donnée
 * financière ni d'identifiant numérique.
 *
 * Types (`type`) : departure_assigned, departure_modified, manifest_stale,
 * terminal_revoked (critique), scan_conflict (critique), operation_rejected,
 * sync_completed, sync_failed, no_show_closed, print_error.
 *
 * `action` dit au client quoi proposer : none, open_departure, refresh_manifest,
 * reauthenticate, review_scan_conflict, review_rejected_operations, retry_sync,
 * check_printer.
 *
 * Récupération fiable : les notifications sont persistées AVANT toute
 * diffusion temps réel. Après une coupure ou un redémarrage, le client
 * appelle `GET /notifications?since=<created_at de la dernière reçue>` et
 * déduplique par `uuid`. Le temps réel (canal privé
 * `ops-notifications.{user_uuid}`, événement `ops.notification.published`)
 * n'est qu'un accélérateur : sans lui, rien n'est perdu.
 *
 * Erreurs : `{ error: { code, message, action } }` — mêmes codes que les
 * sessions OPS (session_expired, session_revoked, permission_denied…).
 */
class OpsNotificationController extends Controller
{
    /**
     * Notifications de l'utilisateur, les plus récentes d'abord.
     *
     * @authenticated
     *
     * @queryParam unread boolean Uniquement les non lues. Example: true
     * @queryParam type string Filtrer par type. Example: scan_conflict
     * @queryParam severity string info, warning ou critical. Example: critical
     * @queryParam since string Date ISO 8601 (inclusive) : reprise après coupure. Example: 2026-09-21T08:00:00Z
     * @queryParam per_page integer 1 à 100 (30 par défaut). Example: 30
     * @queryParam page integer Page. Example: 1
     *
     * @response 200 scenario="Notifications" {
     *   "data": [
     *     {
     *       "uuid": "0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11",
     *       "event_uuid": "5a1f0d43-1b52-4f0a-a7f7-0b6c4d2e9a10",
     *       "type": "scan_conflict",
     *       "severity": "critical",
     *       "title": "Conflit de scan",
     *       "message": "Un billet du départ COT-LFW du 21/09/2026 à 07:30 a été scanné sur deux terminaux (P501-000123 et P501-000124). Vérifiez l’embarquement du passager.",
     *       "action": "review_scan_conflict",
     *       "is_read": false,
     *       "read_at": null,
     *       "created_at": "2026-09-21T07:41:12.000Z",
     *       "occurred_at": "2026-09-21T07:41:12.000Z",
     *       "departure": { "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e", "line_code": "COT-LFW", "date": "2026-09-21", "time": "07:30" },
     *       "terminal": { "uuid": "b3091549-9179-454e-8440-58179f2208df", "serial_number": "P501-000124" },
     *       "data": { "code": "conflict", "accepted_terminal_serial": "P501-000123", "conflicting_terminal_serial": "P501-000124" }
     *     }
     *   ],
     *   "meta": { "current_page": 1, "per_page": 30, "total": 1, "last_page": 1, "unread_count": 1, "critical_unread_count": 1, "server_time": "2026-09-21T07:45:00.000Z" }
     * }
     * @response 401 scenario="Session expirée" {
     *   "error": { "code": "session_expired", "message": "Votre session a expiré. Reconnectez-vous.", "action": "reauthenticate", "reason": "expired" }
     * }
     * @response 403 scenario="Sans permission" {
     *   "error": { "code": "permission_denied", "message": "Votre compte n’a pas la permission d’utiliser KORA OPS Mobile.", "action": "contact_agency" }
     * }
     */
    public function index(ListOpsNotificationsRequest $request, OpsNotificationQuery $notifications): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = $notifications->paginate($user, $request->filters(), $request->perPage());
        $counts = $notifications->counts($user);

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (OpsNotification $notification): array => (new OpsNotificationResource($notification))->resolve($request))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'unread_count' => $counts['unread'],
                'critical_unread_count' => $counts['critical_unread'],
                'server_time' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Nombre de notifications non lues (badge), dont les critiques.
     *
     * @authenticated
     *
     * @response 200 scenario="Compteurs" {
     *   "data": { "unread": 3, "critical_unread": 1 },
     *   "meta": { "server_time": "2026-09-21T07:45:00.000Z" }
     * }
     */
    public function unreadCount(Request $request, OpsNotificationQuery $notifications): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $notifications->counts($user),
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }

    /**
     * Marque une notification lue (idempotent). Introuvable (404) si elle
     * n'appartient pas à l'utilisateur.
     *
     * @authenticated
     *
     * @urlParam notification string required UUID de la notification. Example: 0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11
     *
     * @response 200 scenario="Notification lue" {
     *   "data": { "uuid": "0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11", "type": "scan_conflict", "is_read": true, "read_at": "2026-09-21T07:46:00.000Z" }
     * }
     * @response 404 scenario="Notification d'un autre utilisateur ou inconnue" {
     *   "message": "No query results for model [App\\Modules\\Notifications\\Models\\OpsNotification]."
     * }
     */
    public function markRead(Request $request, string $notification, MarkOpsNotificationReadAction $markRead): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => (new OpsNotificationResource($markRead->execute($user, $notification)))->resolve($request),
        ]);
    }

    /**
     * Marque toutes les notifications non lues comme lues (jusqu'à `before`
     * si fourni : celles reçues depuis restent non lues).
     *
     * @authenticated
     *
     * @bodyParam before string Date ISO 8601 : seules les notifications créées jusque-là sont marquées. Example: 2026-09-21T07:45:00Z
     *
     * @response 200 scenario="Notifications lues" {
     *   "data": { "updated": 3 },
     *   "meta": { "unread_count": 0, "server_time": "2026-09-21T07:46:00.000Z" }
     * }
     */
    public function markAllRead(MarkAllOpsNotificationsReadRequest $request, MarkAllOpsNotificationsReadAction $markAll, OpsNotificationQuery $notifications): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $markAll->execute($user, $request->before());

        return response()->json([
            'data' => ['updated' => $updated],
            'meta' => [
                'unread_count' => $notifications->counts($user)['unread'],
                'server_time' => now()->toISOString(),
            ],
        ]);
    }
}
