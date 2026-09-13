<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Services\TicketVisibility;
use App\Policies\Concerns\AuthorizesPermissions;

class TicketPolicy
{
    use AuthorizesPermissions;

    public function __construct(private readonly TicketVisibility $visibility) {}

    public function viewAny(User $user): bool
    {
        return $this->visibility->canBrowse($user);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->visibility->canView($user, $ticket);
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
