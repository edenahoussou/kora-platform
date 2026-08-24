<?php

namespace App\Modules\SeatInventory\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CounterSaleSeatStatus: string
{
    use HasEnumUtilities;

    case Available = 'available';
    case Reserved = 'reserved';
    case Paid = 'paid';
    case Blocked = 'blocked';
}
