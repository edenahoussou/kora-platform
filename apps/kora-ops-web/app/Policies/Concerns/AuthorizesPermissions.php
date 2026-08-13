<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesPermissions
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->status === 'active' && $user->can($permission);
    }
}
