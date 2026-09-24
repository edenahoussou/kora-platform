<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Actions\HandshakeTerminalAction;
use App\Modules\IdentityAccess\Actions\LogTerminalActivityAction;
use App\Modules\IdentityAccess\Actions\RecordTerminalSyncAction;
use App\Modules\IdentityAccess\Http\Requests\TerminalActivityRequest;
use App\Modules\IdentityAccess\Http\Requests\TerminalHandshakeRequest;
use App\Modules\IdentityAccess\Http\Requests\TerminalSyncRequest;
use App\Modules\IdentityAccess\Http\Resources\TerminalP501Resource;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Services\OpsSessionPayloadBuilder;
use App\Modules\IdentityAccess\Services\OpsSessionTokenReader;
use App\Modules\Notifications\Services\OpsNotifier;
use Illuminate\Http\JsonResponse;

/**
 * @group OPS Mobile — Terminal
 *
 * Handshake et opérations liées au terminal P501 pour l'application OPS
 * Mobile. L'authentification de l'utilisateur (login, PIN, OTP) reste celle
 * de l'app KORA existante (auth:sanctum) ; ces endpoints ajoutent le
 * contrôle du terminal par-dessus une session déjà ouverte.
 */
class TerminalOpsMobileController extends Controller
{
    /**
     * Ouverture de session OPS : à appeler juste après le login pour lier la
     * session (jeton Sanctum) au terminal physique utilisé.
     *
     * Vérifie, dans l'ordre : terminal connu et actif, compte actif, rôle
     * terrain, permission `departures.view`, terminal affecté à
     * l'utilisateur. Ouvrir une session ferme les précédentes de
     * l'utilisateur et du terminal (motif `replaced`).
     *
     * `data` reprend les champs du terminal (contrat historique) et ajoute la
     * session (`expires_at`, `renewable_until`), l'utilisateur et ses
     * permissions OPS (`capabilities`). Après une perte réseau, appeler
     * `POST ops-mobile/session/renew` AVANT `expires_at` ; une session
     * expirée impose de se reconnecter puis de refaire ce handshake.
     *
     * @authenticated
     *
     * @response 200 scenario="Session ouverte" {
     *   "data": {
     *     "uuid": "b3091549-9179-454e-8440-58179f2208df",
     *     "serial_number": "P501-000123",
     *     "type": "p501",
     *     "status": "active",
     *     "last_sync_at": null,
     *     "last_handshake_at": "2026-09-14T09:00:00.000Z",
     *     "session": {
     *       "uuid": "0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11",
     *       "state": "active",
     *       "opened_at": "2026-09-14T09:00:00.000Z",
     *       "expires_at": "2026-09-14T21:00:00.000Z",
     *       "renewable_until": "2026-09-15T09:00:00.000Z",
     *       "renewed_at": null,
     *       "renewal_count": 0
     *     },
     *     "capabilities": { "departures_view": true, "boarding_scan": true, "seat_release": true },
     *     "user": { "uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5", "name": "Marc Adjagba", "status": "active", "permissions": ["departures.view", "boarding.scan"] }
     *   },
     *   "meta": { "server_time": "2026-09-14T09:00:00.000Z" }
     * }
     * @response 404 scenario="Terminal inconnu" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n’est pas enregistré. Contactez votre agence.", "action": "contact_agency" }
     * }
     * @response 403 scenario="Terminal perdu" {
     *   "error": { "code": "terminal_lost", "message": "Ce terminal a été déclaré perdu. Il ne peut plus être utilisé.", "action": "contact_agency" }
     * }
     * @response 403 scenario="Terminal affecté à un autre utilisateur" {
     *   "error": { "code": "terminal_not_assigned", "message": "Ce terminal ne vous est pas affecté. Contactez votre agence.", "action": "contact_agency" }
     * }
     * @response 403 scenario="Compte sans rôle OPS" {
     *   "error": { "code": "role_not_authorized", "message": "Votre compte n’a pas de rôle autorisé sur KORA OPS Mobile.", "action": "contact_agency" }
     * }
     */
    public function handshake(
        TerminalHandshakeRequest $request,
        HandshakeTerminalAction $handshakeTerminal,
        OpsSessionTokenReader $tokens,
        OpsSessionPayloadBuilder $payload,
    ): JsonResponse {
        $session = $handshakeTerminal->execute(
            $request->serialNumber(),
            $request->user(),
            $tokens->tokenId($request),
            $request->ip(),
            $request->telemetry(),
        );

        return response()->json([
            'data' => $payload->build($session, $session->terminal, $request->user(), $request),
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }

    /**
     * Accuse réception d'une synchronisation réussie côté terminal et met
     * à jour last_sync_at. Le traitement du contenu du lot de
     * synchronisation (sync_batches/sync_operations) est hors périmètre.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal (alternative au champ serial_number du corps). Example: P501-000123
     *
     * @bodyParam serial_number string Numéro de série du terminal, requis si l'en-tête X-Terminal-Serial est absent. Example: P501-000123
     * @bodyParam summary object Résumé libre de ce qui a été synchronisé (compté, jamais interprété par ce endpoint). No-example.
     *
     * @response 200 scenario="Synchronisation enregistrée" {
     *   "data": {
     *     "uuid": "b3091549-9179-454e-8440-58179f2208df",
     *     "serial_number": "P501-000123",
     *     "status": "active",
     *     "last_sync_at": "2026-09-14T09:05:00.000Z",
     *     "last_handshake_at": "2026-09-14T09:00:00.000Z"
     *   }
     * }
     * @response 403 scenario="Terminal non utilisable" {
     *   "error": { "code": "terminal_inactive", "message": "Ce terminal est désactivé. Contactez votre agence." }
     * }
     * @response 404 scenario="Terminal unknown" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n’est pas enregistré. Contactez votre agence." }
     * }
     */
    public function sync(TerminalSyncRequest $request, RecordTerminalSyncAction $recordSync): JsonResponse
    {
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $terminal = $recordSync
            ->execute(
                $terminal,
                $request->user(),
                $request->summary(),
                $request->telemetry(),
            )
            ->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    /**
     * Journalise un scan, une impression ou une erreur pour le terminal en
     * cours d'utilisation.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal (alternative au champ serial_number du corps). Example: P501-000123
     *
     * @response 204 scenario="Activité journalisée"
     * @response 403 scenario="Terminal non utilisable" {
     *   "error": { "code": "terminal_inactive", "message": "Ce terminal est désactivé. Contactez votre agence." }
     * }
     * @response 422 scenario="Type non autorisé (handshake/sync ont leur propre endpoint)" {
     *   "message": "The selected type is invalid.",
     *   "errors": { "type": ["The selected type is invalid."] }
     * }
     */
    public function activity(TerminalActivityRequest $request, LogTerminalActivityAction $logActivity, OpsNotifier $notifier): JsonResponse
    {
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $logActivity->execute($terminal, $request->user(), $request->type(), $request->context());

        return response()->json(status: 204);
    }
}
