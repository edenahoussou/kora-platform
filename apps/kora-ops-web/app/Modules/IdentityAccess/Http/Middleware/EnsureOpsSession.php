<?php

namespace App\Modules\IdentityAccess\Http\Middleware;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\LogOpsSessionRefusalAction;
use App\Modules\IdentityAccess\Actions\ResolveTerminalBySerialAction;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Actions\RevokeUserOpsSessionsAction;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Exceptions\TerminalNotUsableException;
use App\Modules\IdentityAccess\Exceptions\TerminalUnknownException;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
use App\Modules\IdentityAccess\Services\OpsSessionTokenReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de toute route OPS Mobile (départs, manifeste, scan, libération de
 * siège, synchronisation, journal du terminal). Remplace `terminal.active` :
 * en plus du terminal, elle exige une session OPS ouverte (handshake) pour
 * le jeton présenté.
 *
 * Contrôles, dans cet ordre (le premier échec répond, avec un code
 * fonctionnel exploitable par le mobile) :
 *  1. terminal connu et actif (terminal_unknown / terminal_inactive /
 *     terminal_maintenance / terminal_lost) — numéro de série dans
 *     X-Terminal-Serial (à défaut `serial_number`) ;
 *  2. compte actif, rôle terrain, permission (account_suspended /
 *     account_locked / role_not_authorized / permission_denied) ;
 *  3. session ouverte pour ce jeton (session_not_opened), non révoquée
 *     (session_revoked), non expirée (session_expired) ;
 *  4. session liée à CE terminal (terminal_mismatch) ;
 *  5. terminal affecté à cet utilisateur (terminal_not_assigned).
 *
 * Une session trouvée expirée est révoquée sur-le-champ (jeton supprimé) :
 * le terminal doit se reconnecter, jamais réutiliser silencieusement la
 * session. Chaque refus est journalisé (`ops_session_refused`). Le terminal
 * et la session sont mis à disposition via $request->attributes
 * (`terminal`, `ops_session`).
 */
class EnsureOpsSession
{
    public function __construct(
        private ResolveTerminalBySerialAction $resolveTerminal,
        private OpsSessionGuard $guard,
        private OpsSessionTokenReader $tokens,
        private RevokeOpsSessionAction $revokeSession,
        private RevokeUserOpsSessionsAction $revokeUserSessions,
        private LogOpsSessionRefusalAction $logRefusal,
    ) {}

    /**
     * @throws TerminalUnknownException|TerminalNotUsableException|OpsSessionException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $serialNumber = (string) ($request->header('X-Terminal-Serial') ?? $request->input('serial_number'));
        $candidate = $request->user();
        $user = $candidate instanceof User ? $candidate : null;
        $terminal = null;

        try {
            $terminal = $this->resolveTerminal->execute($serialNumber);

            if (! $terminal->isUsable()) {
                throw new TerminalNotUsableException($terminal);
            }

            if ($user === null) {
                throw new OpsSessionException(OpsSessionErrorCode::SessionInvalid);
            }

            $this->assertUserAllowed($user);
            $session = $this->resolveSession($request, $user, $terminal);
        } catch (OpsSessionException|TerminalNotUsableException|TerminalUnknownException $exception) {
            $this->logRefusal->execute($exception, $user, $terminal, $serialNumber, $request->ip());

            throw $exception;
        }

        $this->touch($session);

        $request->attributes->set('terminal', $terminal);
        $request->attributes->set('ops_session', $session);

        return $next($request);
    }

    /**
     * @throws OpsSessionException
     */
    private function assertUserAllowed(User $user): void
    {
        try {
            $this->guard->assertUserAllowed($user);
        } catch (OpsSessionException $exception) {
            $reason = match ($exception->errorCode) {
                OpsSessionErrorCode::AccountSuspended => OpsSessionRevocationReason::AccountSuspended,
                OpsSessionErrorCode::RoleNotAuthorized, OpsSessionErrorCode::PermissionDenied => OpsSessionRevocationReason::AccessRemoved,
                default => null,
            };

            // Un compte verrouillé temporairement (PIN) n'est que refusé : ne
            // pas laisser des saisies de PIN erronées couper une session
            // légitime. Suspension ou perte d'accès : les sessions tombent.
            if ($reason !== null) {
                $this->revokeUserSessions->execute($user, $reason);
            }

            throw $exception;
        }
    }

    /**
     * @throws OpsSessionException
     */
    private function resolveSession(Request $request, User $user, TerminalP501 $terminal): TerminalSession
    {
        $tokenId = $this->tokens->tokenId($request);

        $session = $tokenId === null ? null : TerminalSession::query()
            ->where('personal_access_token_id', $tokenId)
            ->latest('id')
            ->first();

        if ($session === null) {
            throw new OpsSessionException(OpsSessionErrorCode::SessionNotOpened);
        }

        if ($session->isRevoked()) {
            throw new OpsSessionException(
                $session->revoked_reason === OpsSessionRevocationReason::Expired->value
                    ? OpsSessionErrorCode::SessionExpired
                    : OpsSessionErrorCode::SessionRevoked,
                $session->revoked_reason,
            );
        }

        if ($session->user_id !== $user->id) {
            throw new OpsSessionException(OpsSessionErrorCode::SessionInvalid);
        }

        if ($session->isExpired()) {
            $this->revokeSession->execute($session, OpsSessionRevocationReason::Expired, $user);

            throw new OpsSessionException(OpsSessionErrorCode::SessionExpired, OpsSessionRevocationReason::Expired->value);
        }

        if ($session->terminal_p501_id !== $terminal->id) {
            throw new OpsSessionException(OpsSessionErrorCode::TerminalMismatch);
        }

        if ($terminal->assigned_user_id !== $user->id) {
            $this->revokeSession->execute($session, OpsSessionRevocationReason::TerminalReassigned, $user);

            throw new OpsSessionException(OpsSessionErrorCode::TerminalNotAssigned);
        }

        return $session;
    }

    private function touch(TerminalSession $session): void
    {
        $interval = (int) config('ops_session.touch_interval_seconds', 60);

        if ($session->last_seen_at === null || $session->last_seen_at->lte(now()->subSeconds($interval))) {
            $session->forceFill(['last_seen_at' => now()])->save();
        }
    }
}
