<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;

class ArchiveUserAction
{
    public function __construct(
        private readonly RevokeUserAccessAction $revokeAccess,
    ) {}

    public function execute(User $user, User $actor): void
    {
        $user->forceFill(['status' => UserStatus::Suspended])->save();
        $user->delete();
        $this->revokeAccess->execute($user, $actor);

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('user_archived');
    }
}
