<?php

namespace App\Modules\Ticketing\Services;

use App\Models\User;
use App\Modules\Ticketing\Models\Ticket;
use App\Policies\Concerns\AuthorizesPermissions;
use Illuminate\Database\Eloquent\Builder;

class TicketVisibility
{
    use AuthorizesPermissions;

    public function canBrowse(User $user): bool
    {
        return $this->allows($user, 'tickets.view')
            && ($user->agency_id !== null || $this->allows($user, 'tickets.view-network'));
    }

    /** @return Builder<Ticket> */
    public function query(User $user): Builder
    {
        $query = Ticket::query();
        if (! $this->canBrowse($user)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->agency_id !== null) {
            $query->whereHas('reservation', fn (Builder $reservation) => $reservation->where('selling_agency_id', $user->agency_id));
        }

        return $query;
    }

    public function canView(User $user, Ticket $ticket): bool
    {
        return $this->query($user)->whereKey($ticket->getKey())->exists();
    }
}
