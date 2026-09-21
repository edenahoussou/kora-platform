<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Role;
use App\Policies\Concerns\AuthorizesPermissions;

class RolePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function restore(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage');
    }
}
