<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;

use function activity;

class RenewOpsSessionAction
{
    public function __construct(
        private RevokeOpsSessionAction $revokeSession,
    ) {}

    /**
     * Prolonge la fenêtre d'une session encore valide (`ttl_minutes` à partir
     * de maintenant), sans jamais dépasser la durée absolue fixée à
     * l'ouverture. L'appelant (middleware `ops.session`) a déjà revérifié le
     * compte, le rôle, le terminal et son affectation.
     *
     * Une session expirée n'est JAMAIS prolongée : elle est révoquée (motif
     * `expired`) et le mobile doit se reconnecter — c'est la reprise
     * contrôlée après une perte réseau prolongée.
     *
     * @throws OpsSessionException Session expirée.
     */
    public function execute(TerminalSession $session, User $user, TerminalP501 $terminal): TerminalSession
    {
        $now = now();

        if ($session->isExpired($now)) {
            $this->revokeSession->execute($session, OpsSessionRevocationReason::Expired, $user);

            throw new OpsSessionException(OpsSessionErrorCode::SessionExpired, OpsSessionRevocationReason::Expired->value);
        }

        $candidate = $now->copy()->addMinutes((int) config('ops_session.ttl_minutes', 720));
        $ceiling = $session->absolute_expires_at;
        $expiresAt = $ceiling !== null && $candidate->gt($ceiling) ? $ceiling->copy() : $candidate;

        $session->forceFill([
            'expires_at' => $expiresAt,
            'renewed_at' => $now,
            'last_seen_at' => $now,
            'renewal_count' => $session->renewal_count + 1,
        ])->save();

        activity()
            ->performedOn($terminal)
            ->causedBy($user)
            ->withProperties([
                'session_uuid' => $session->uuid,
                'serial_number' => $terminal->serial_number,
                'expires_at' => $expiresAt->toISOString(),
                'renewal_count' => $session->renewal_count,
            ])
            ->log('ops_session_renewed');

        return $session;
    }
}
