<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum LayoutCellKind: string
{
    use HasEnumUtilities;

    case Seat = 'seat';
    case Aisle = 'aisle';
    case Empty = 'empty';
    case Door = 'door';
    case Driver = 'driver';
}
