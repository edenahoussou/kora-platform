<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Policies\Concerns\AuthorizesPermissions;

class AgencyPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'agencies.view');
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->allows($user, 'agencies.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'agencies.create');
    }

    public function update(User $user, Agency $agency): bool
    {
        return $this->allows($user, 'agencies.update');
    }

    public function deactivate(User $user, Agency $agency): bool
    {
        return $this->allows($user, 'agencies.deactivate');
    }
}
