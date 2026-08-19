<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\BusNetwork\Models\Stop;
use App\Policies\Concerns\AuthorizesPermissions;

class StopPolicy
{
    use AuthorizesPermissions;

    public function create(User $user): bool
    {
        return $this->allows($user, 'network.create');
    }

    public function update(User $user, Stop $stop): bool
    {
        return $this->allows($user, 'network.update');
    }

    public function deactivate(User $user, Stop $stop): bool
    {
        return $this->allows($user, 'network.deactivate');
    }
}
