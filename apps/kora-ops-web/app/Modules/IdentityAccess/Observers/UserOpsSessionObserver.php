<?php

namespace App\Modules\IdentityAccess\Observers;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeUserOpsSessionsAction;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\UserStatus;

use function Illuminate\Support\enum_value;

/**
 * Ferme immédiatement les sessions OPS d'un compte qui vient d'être suspendu :
 * la révocation d'un compte invalide ses sessions sans attendre sa prochaine
 * requête. (La perte d'un rôle ou d'une permission est, elle, détectée à
 * chaque requête OPS par le middleware `ops.session`.)
 */
class UserOpsSessionObserver
{
    public function __construct(
        private RevokeUserOpsSessionsAction $revokeUserSessions,
    ) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged('status') && enum_value($user->status) === UserStatus::Suspended->value) {
            $this->revokeUserSessions->execute($user, OpsSessionRevocationReason::AccountSuspended);
        }
    }
}
