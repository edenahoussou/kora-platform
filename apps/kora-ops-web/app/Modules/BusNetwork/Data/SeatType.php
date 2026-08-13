<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SeatType: string
{
    use HasEnumUtilities;

    case Standard = 'standard';
    case Crew = 'crew';
    case Premium = 'premium';
}
