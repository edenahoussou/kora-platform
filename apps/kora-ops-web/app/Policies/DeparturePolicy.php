<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Policies\Concerns\AuthorizesPermissions;

class DeparturePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'departures.view');
    }

    public function view(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function update(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function recordDelay(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function delete(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.cancel');
    }
}
