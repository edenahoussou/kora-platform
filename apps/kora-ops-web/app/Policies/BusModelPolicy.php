<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\BusNetwork\Models\BusModel;
use App\Policies\Concerns\AuthorizesPermissions;

class BusModelPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'fleet.view');
    }

    public function view(User $user, BusModel $model): bool
    {
        return $this->allows($user, 'fleet.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'fleet.create');
    }

    public function update(User $user, BusModel $model): bool
    {
        return $this->allows($user, 'fleet.update');
    }

    public function deactivate(User $user, BusModel $model): bool
    {
        return $this->allows($user, 'fleet.deactivate');
    }
}
