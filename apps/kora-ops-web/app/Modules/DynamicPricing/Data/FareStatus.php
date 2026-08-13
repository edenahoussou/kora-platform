<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum FareStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';
}
