<?php

namespace App\Modules\Notifications\Http\Middleware;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
use App\Modules\IdentityAccess\Services\OpsSessionTokenReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde commune des notifications, pour OPS Mobile et OPS Web : compte actif
 * et permission `notifications.view`. Un jeton lié à une session OPS
 * (terminal P501, MEZ-95) doit en plus porter une session valide — mais le
 * numéro de série du terminal n'est pas exigé, pour que la récupération
 * reste possible juste après une reconnexion.
 *
 * Mêmes codes d'erreur que le reste de l'API OPS : le mobile sait
 * déterministiquement s'il doit se reconnecter.
 */
class EnsureNotificationsAccess
{
    public function __construct(
        private OpsSessionTokenReader $tokens,
        private OpsSessionGuard $guard,
        private RevokeOpsSessionAction $revokeSession,
    ) {}

    /**
     * @throws OpsSessionException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new OpsSessionException(OpsSessionErrorCode::SessionInvalid);
        }

        if ($user->status === UserStatus::Suspended) {
            throw new OpsSessionException(OpsSessionErrorCode::AccountSuspended);
        }

        if ($user->status === UserStatus::Locked) {
            throw new OpsSessionException(OpsSessionErrorCode::AccountLocked);
        }

        if (! $user->can('notifications.view')) {
            throw new OpsSessionException(OpsSessionErrorCode::PermissionDenied);
        }

        $this->assertBoundSessionIsValid($request, $user);

        return $next($request);
    }

    /**
     * @throws OpsSessionException
     */
    private function assertBoundSessionIsValid(Request $request, User $user): void
    {
        $tokenId = $this->tokens->tokenId($request);

        $session = $tokenId === null ? null : TerminalSession::query()
            ->where('personal_access_token_id', $tokenId)
            ->latest('id')
            ->first();

        // Jeton non lié à un terminal (OPS Web, appli passager) : rien de plus à vérifier.
        if ($session === null) {
            return;
        }

        if ($session->isRevoked()) {
            throw new OpsSessionException(
                $session->revoked_reason === OpsSessionRevocationReason::Expired->value
                    ? OpsSessionErrorCode::SessionExpired
                    : OpsSessionErrorCode::SessionRevoked,
                $session->revoked_reason,
            );
        }

        if ($session->isExpired()) {
            $this->revokeSession->execute($session, OpsSessionRevocationReason::Expired, $user);

            throw new OpsSessionException(OpsSessionErrorCode::SessionExpired, OpsSessionRevocationReason::Expired->value);
        }

        $this->guard->assertUserAllowed($user);
    }
}
