<?php

namespace App\Modules\Ticketing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TicketStatus: string
{
    use HasEnumUtilities;

    case Issued = 'issued';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
