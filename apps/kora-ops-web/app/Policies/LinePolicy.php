<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Policies\Concerns\AuthorizesPermissions;

class LinePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'network.view');
    }

    public function view(User $user, Line $line): bool
    {
        return $this->allows($user, 'network.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'network.create');
    }

    public function update(User $user, Line $line): bool
    {
        return $this->allows($user, 'network.update');
    }

    public function deactivate(User $user, Line $line): bool
    {
        return $this->allows($user, 'network.deactivate');
    }
}
