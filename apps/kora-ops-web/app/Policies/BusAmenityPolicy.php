<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\BusNetwork\Models\BusAmenity;
use App\Policies\Concerns\AuthorizesPermissions;

class BusAmenityPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'fleet.view');
    }

    public function view(User $user, BusAmenity $amenity): bool
    {
        return $this->allows($user, 'fleet.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'fleet.create');
    }

    public function update(User $user, BusAmenity $amenity): bool
    {
        return $this->allows($user, 'fleet.update');
    }

    public function deactivate(User $user, BusAmenity $amenity): bool
    {
        return $this->allows($user, 'fleet.deactivate');
    }
}
