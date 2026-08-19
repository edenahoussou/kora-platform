<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ServiceClassStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Archived = 'archived';
}
