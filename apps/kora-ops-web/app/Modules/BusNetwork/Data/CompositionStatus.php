<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CompositionStatus: string
{
    use HasEnumUtilities;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
