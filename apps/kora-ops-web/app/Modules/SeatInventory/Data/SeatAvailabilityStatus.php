<?php

namespace App\Modules\SeatInventory\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SeatAvailabilityStatus: string
{
    use HasEnumUtilities;

    case Available = 'available';
    case Held = 'held';
    case Occupied = 'occupied';
    case Blocked = 'blocked';
}
