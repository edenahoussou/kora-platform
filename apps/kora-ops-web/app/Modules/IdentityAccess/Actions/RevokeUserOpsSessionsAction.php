<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Models\TerminalSession;

class RevokeUserOpsSessionsAction
{
    public function __construct(
        private RevokeOpsSessionAction $revokeSession,
    ) {}

    /**
     * Révoque toutes les sessions OPS Mobile actives d'un utilisateur
     * (compte suspendu, rôle ou permission retiré…).
     *
     * @return int Nombre de sessions révoquées.
     */
    public function execute(User $user, OpsSessionRevocationReason|string $reason, ?User $actor = null): int
    {
        $sessions = TerminalSession::query()
            ->active()
            ->where('user_id', $user->id)
            ->get();

        foreach ($sessions as $session) {
            $this->revokeSession->execute($session, $reason, $actor);
        }

        return $sessions->count();
    }
}
