<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Ticketing\Models\Ticket;
use App\Policies\Concerns\AuthorizesPermissions;

class TicketPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.view');
    }

    public function issue(User $user): bool
    {
        return $this->allows($user, 'tickets.issue');
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        return $this->allows($user, 'tickets.cancel');
    }
}
