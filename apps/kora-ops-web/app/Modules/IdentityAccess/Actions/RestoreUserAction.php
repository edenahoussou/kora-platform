<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;

class RestoreUserAction
{
    public function execute(User $user, User $actor): User
    {
        $user->restore();
        $user->forceFill([
            'status' => UserStatus::Suspended,
            'failed_pin_attempts' => 0,
            'locked_until' => null,
        ])->save();

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('user_restored');

        return $user->refresh();
    }
}
