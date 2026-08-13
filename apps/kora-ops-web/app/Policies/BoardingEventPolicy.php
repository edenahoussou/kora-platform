<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Policies\Concerns\AuthorizesPermissions;

class BoardingEventPolicy
{
    use AuthorizesPermissions;

    public function create(User $user): bool
    {
        return $this->allows($user, 'boarding.scan');
    }

    public function reverse(User $user, BoardingEvent $boardingEvent): bool
    {
        return $this->allows($user, 'boarding.reverse');
    }
}
