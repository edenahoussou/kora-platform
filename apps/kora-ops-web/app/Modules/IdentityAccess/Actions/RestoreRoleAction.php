<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\Role;

class RestoreRoleAction
{
    public function execute(Role $role, mixed $actor): Role
    {
        $role->forceFill(['is_active' => true])->save();

        activity('identity-access')
            ->performedOn($role)
            ->causedBy($actor)
            ->log('role_restored');

        return $role->refresh();
    }
}
