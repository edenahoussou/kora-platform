<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum BusModelStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Archived = 'archived';
}
