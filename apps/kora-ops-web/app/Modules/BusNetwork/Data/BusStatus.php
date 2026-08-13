<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum BusStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Draft = 'draft';
    case Maintenance = 'maintenance';
    case Retired = 'retired';
}
