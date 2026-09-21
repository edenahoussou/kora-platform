<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Models\Role;

class UserRoleResolver
{
    public function resolve(string $code): Role
    {
        return Role::query()
            ->where('name', $code)
            ->where('guard_name', 'web')
            ->where('is_active', true)
            ->firstOrFail();
    }
}
