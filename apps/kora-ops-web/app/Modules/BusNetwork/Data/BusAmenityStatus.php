<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum BusAmenityStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Archived = 'archived';
}
