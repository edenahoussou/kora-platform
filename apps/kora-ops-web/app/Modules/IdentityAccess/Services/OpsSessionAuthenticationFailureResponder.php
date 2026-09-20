<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\IdentityAccess\Actions\LogOpsSessionRefusalAction;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Remplace le 401 générique « Unauthenticated. » de Sanctum sur les routes OPS
 * Mobile et notifications par un état d'authentification déterministe (`session_expired`,
 * `session_revoked` avec son motif, ou `session_invalid`) : le mobile sait
 * s'il doit se reconnecter, sans deviner d'après un message.
 *
 * Le jeton révoqué est supprimé, mais sa session conserve son identifiant :
 * c'est elle qui permet de distinguer une révocation d'un jeton inconnu, et de
 * journaliser le refus (`ops_session_refused`) au nom de son titulaire.
 */
class OpsSessionAuthenticationFailureResponder
{
    public function __construct(
        private OpsSessionTokenReader $tokens,
        private LogOpsSessionRefusalAction $logRefusal,
    ) {}

    /**
     * @return JsonResponse|null null hors des routes OPS Mobile (rendu par défaut).
     */
    public function respond(Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1/ops/*', 'api/v1/ops-mobile/*', 'api/v1/notifications', 'api/v1/notifications/*')) {
            return null;
        }

        $tokenId = $this->tokens->tokenId($request);
        $session = $tokenId === null ? null : TerminalSession::query()
            ->where('personal_access_token_id', $tokenId)
            ->latest('id')
            ->first();

        $code = OpsSessionErrorCode::SessionInvalid;
        $reason = null;

        if ($session !== null) {
            if ($session->isRevoked()) {
                $reason = $session->revoked_reason;
                $code = $reason === OpsSessionRevocationReason::Expired->value
                    ? OpsSessionErrorCode::SessionExpired
                    : OpsSessionErrorCode::SessionRevoked;
            } elseif ($session->isExpired()) {
                $code = OpsSessionErrorCode::SessionExpired;
            }
        } elseif ($tokenId !== null) {
            $token = PersonalAccessToken::query()->find($tokenId);

            if ($token !== null && $token->expires_at !== null && $token->expires_at->isPast()) {
                $code = OpsSessionErrorCode::SessionExpired;
            }
        }

        $exception = new OpsSessionException($code, $reason);

        // Journalisé seulement quand une session identifie le porteur : un jeton
        // inconnu ou absent (trafic anonyme) ne doit pas écrire en base à chaque appel.
        if ($session !== null) {
            $this->logRefusal->execute($exception, $session->user, $session->terminal, '', $request->ip());
        }

        return $exception->render($request);
    }
}
