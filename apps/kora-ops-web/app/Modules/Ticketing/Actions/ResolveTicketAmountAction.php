<?php

namespace App\Modules\Ticketing\Actions;

use App\Modules\Ticketing\Models\Ticket;

class ResolveTicketAmountAction
{
    public function execute(Ticket $ticket): int
    {
        $index = $ticket->reservationPassenger?->passenger_index;

        return $index === null ? 0 : (int) ($ticket->reservation?->total_snapshot['passenger_allocations'][$index] ?? 0);
    }
}
