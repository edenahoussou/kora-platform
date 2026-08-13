<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;

use function Illuminate\Support\enum_value;

trait AuthorizesPermissions
{
    protected function allows(User $user, string $permission): bool
    {
        return enum_value($user->status) === UserStatus::Active->value && $user->can($permission);
    }
}
