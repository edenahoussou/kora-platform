<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;

class UnlockUserAction
{
    public function execute(User $user, User $actor): User
    {
        $status = $user->trashed() ? UserStatus::Suspended : UserStatus::Active;

        $user->forceFill([
            'failed_pin_attempts' => 0,
            'locked_until' => null,
            'status' => $status,
        ])->save();

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties(['status' => $status->value])
            ->log('user_unlocked');

        return $user->refresh();
    }
}
