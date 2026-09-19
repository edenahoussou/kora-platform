<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Actions\RenewOpsSessionAction;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Services\OpsSessionPayloadBuilder;
use App\Modules\IdentityAccess\Services\OpsSessionTokenReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Mobile — Session
 *
 * Cycle de vie de la session OPS liée à un terminal P501 : consultation de
 * l'état, renouvellement et déconnexion. L'ouverture est le handshake
 * (`POST ops-mobile/terminal/handshake`).
 *
 * Toute réponse de refus a la forme `{ error: { code, message, action, reason? } }`.
 * `action` dit au mobile quoi faire :
 * - `reauthenticate` : retour à l'écran de connexion (session_expired, session_revoked, session_invalid) ;
 * - `open_session` : refaire le handshake (session_not_opened, terminal_mismatch) ;
 * - `contact_agency` : refus durable (terminal_unknown, terminal_inactive, terminal_lost, terminal_not_assigned, role_not_authorized, permission_denied, account_suspended) ;
 * - `retry_later` : refus temporaire (terminal_maintenance, account_locked).
 *
 * `reason` (session_revoked) : logout, terminal_deactivated, terminal_lost, terminal_maintenance,
 * terminal_reassigned, account_suspended, access_removed, replaced, expired.
 */
class OpsSessionController extends Controller
{
    /**
     * État de la session courante. À appeler au retour du réseau ou au
     * démarrage de l'app pour savoir de façon déterministe s'il faut
     * continuer, renouveler ou se reconnecter.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @response 200 scenario="Session active" {
     *   "data": {
     *     "uuid": "b3091549-9179-454e-8440-58179f2208df",
     *     "serial_number": "P501-000123",
     *     "status": "active",
     *     "session": { "uuid": "0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11", "state": "active", "expires_at": "2026-09-14T21:00:00.000Z", "renewable_until": "2026-09-15T09:00:00.000Z" },
     *     "capabilities": { "departures_view": true, "boarding_scan": true, "seat_release": true }
     *   },
     *   "meta": { "server_time": "2026-09-14T10:00:00.000Z" }
     * }
     * @response 401 scenario="Session expirée" {
     *   "error": { "code": "session_expired", "message": "Votre session a expiré. Reconnectez-vous.", "action": "reauthenticate", "reason": "expired" }
     * }
     * @response 401 scenario="Session révoquée" {
     *   "error": { "code": "session_revoked", "message": "Votre session a été fermée. Reconnectez-vous.", "action": "reauthenticate", "reason": "terminal_lost" }
     * }
     * @response 403 scenario="Autre terminal" {
     *   "error": { "code": "terminal_mismatch", "message": "Cette session est liée à un autre terminal. Ouvrez une session sur ce terminal.", "action": "open_session" }
     * }
     */
    public function show(Request $request, OpsSessionPayloadBuilder $payload): JsonResponse
    {
        /** @var TerminalSession $session */
        $session = $request->attributes->get('ops_session');
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');
        /** @var User $user */
        $user = $request->user();

        return $this->respond($payload->build($session, $terminal, $user, $request));
    }

    /**
     * Renouvelle la session : prolonge la fenêtre (12 h par défaut) sans
     * jamais dépasser `renewable_until` (durée absolue de 24 h par défaut).
     * Le compte, le rôle, le terminal et son affectation sont revérifiés. Une
     * session expirée n'est JAMAIS prolongée : le mobile doit se reconnecter.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @response 200 scenario="Session renouvelée" {
     *   "data": {
     *     "session": { "uuid": "0c9d3e2a-6c0f-4f57-8d0e-0d6a3c1f5b11", "state": "active", "expires_at": "2026-09-14T22:00:00.000Z", "renewable_until": "2026-09-15T09:00:00.000Z", "renewal_count": 1 }
     *   },
     *   "meta": { "server_time": "2026-09-14T10:00:00.000Z" }
     * }
     * @response 401 scenario="Session expirée (aucune prolongation silencieuse)" {
     *   "error": { "code": "session_expired", "message": "Votre session a expiré. Reconnectez-vous.", "action": "reauthenticate", "reason": "expired" }
     * }
     * @response 403 scenario="Terminal en maintenance" {
     *   "error": { "code": "terminal_maintenance", "message": "Ce terminal est en maintenance. Réessayez plus tard.", "action": "retry_later" }
     * }
     */
    public function renew(Request $request, RenewOpsSessionAction $renewSession, OpsSessionPayloadBuilder $payload): JsonResponse
    {
        /** @var TerminalSession $session */
        $session = $request->attributes->get('ops_session');
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');
        /** @var User $user */
        $user = $request->user();

        $session = $renewSession->execute($session, $user, $terminal);

        return $this->respond($payload->build($session, $terminal, $user, $request));
    }

    /**
     * Déconnexion : ferme la session OPS du jeton courant (motif `logout`) et
     * supprime ce jeton. Idempotent, sans contrôle du terminal : un convoyeur
     * doit toujours pouvoir se déconnecter, même terminal en maintenance.
     *
     * @authenticated
     *
     * @response 204 scenario="Session fermée"
     * @response 401 scenario="Jeton inconnu ou déjà révoqué" {
     *   "error": { "code": "session_invalid", "message": "Session invalide. Reconnectez-vous.", "action": "reauthenticate" }
     * }
     */
    public function destroy(Request $request, OpsSessionTokenReader $tokens, RevokeOpsSessionAction $revokeSession): JsonResponse
    {
        $user = $request->user();
        $tokenId = $tokens->tokenId($request);

        if ($user instanceof User && $tokenId !== null) {
            $sessions = TerminalSession::query()
                ->active()
                ->where('personal_access_token_id', $tokenId)
                ->where('user_id', $user->id)
                ->get();

            foreach ($sessions as $session) {
                $revokeSession->execute($session, OpsSessionRevocationReason::Logout, $user);
            }
        }

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['server_time' => now()->toISOString()],
        ]);
    }
}
