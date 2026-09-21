<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Models\Role;

class DeactivateRoleAction
{
    public function execute(Role $role, mixed $actor): void
    {
        abort_if($role->is_system, 422, 'Les rôles système ne peuvent pas être désactivés.');

        abort_if($role->users()->exists(), 422, 'Ce rôle est encore attribué à des utilisateurs.');

        $role->forceFill(['is_active' => false])->save();

        activity('identity-access')
            ->performedOn($role)
            ->causedBy($actor)
            ->log('role_deactivated');
    }
}
