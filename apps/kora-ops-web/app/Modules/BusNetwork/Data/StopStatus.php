<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum StopStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';
}
