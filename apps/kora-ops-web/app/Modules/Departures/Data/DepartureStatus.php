<?php

namespace App\Modules\Departures\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum DepartureStatus: string
{
    use HasEnumUtilities;

    case Scheduled = 'scheduled';
    case Boarding = 'boarding';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
}
