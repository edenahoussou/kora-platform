<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use Illuminate\Support\Facades\DB;

class RevokeUserAccessAction
{
    public function __construct(
        private readonly RevokeUserOpsSessionsAction $revokeUserSessions,
    ) {}

    public function execute(User $user, ?User $actor = null): void
    {
        $user->tokens()->delete();
        $user->devices()->where('status', UserDeviceStatus::Active->value)->update([
            'status' => UserDeviceStatus::Revoked->value,
            'revoked_at' => now(),
        ]);

        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        $this->revokeUserSessions->execute(
            $user,
            OpsSessionRevocationReason::AccessRemoved,
            $actor,
        );
    }
}
