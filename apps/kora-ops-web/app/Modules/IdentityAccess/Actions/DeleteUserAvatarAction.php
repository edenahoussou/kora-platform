<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;

use function activity;

class DeleteUserAvatarAction
{
    /**
     * Supprime l'avatar courant de l'utilisateur.
     */
    public function execute(User $user): void
    {
        unset($user->media);

        $user->clearMediaCollection('avatar');

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->log('avatar_deleted');
    }
}
