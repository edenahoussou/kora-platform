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

    /**
     * Annulation exceptionnelle d'un embarquement — permission dédiée,
     * distincte de `boarding.scan` (le convoyeur qui scanne n'a pas le
     * droit de défaire un embarquement lui-même).
     */
    public function reverse(User $user, BoardingEvent $boardingEvent): bool
    {
        return $this->allows($user, 'boarding.reverse');
    }
}
