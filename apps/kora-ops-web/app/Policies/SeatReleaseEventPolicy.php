<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

class SeatReleaseEventPolicy
{
    use AuthorizesPermissions;

    public function create(User $user): bool
    {
        return $this->allows($user, 'seat-releases.create');
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'seat-releases.view');
    }
}
