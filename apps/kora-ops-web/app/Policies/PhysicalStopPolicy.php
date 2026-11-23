<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Policies\Concerns\AuthorizesPermissions;

class PhysicalStopPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'network.view');
    }

    public function view(User $user, PhysicalStop $physicalStop): bool
    {
        return $this->allows($user, 'network.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'network.create');
    }

    public function update(User $user, PhysicalStop $physicalStop): bool
    {
        return $this->allows($user, 'network.update');
    }

    public function delete(User $user, PhysicalStop $physicalStop): bool
    {
        return $this->allows($user, 'network.deactivate');
    }
}
