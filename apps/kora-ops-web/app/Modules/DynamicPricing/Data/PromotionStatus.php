<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PromotionStatus: string
{
    use HasEnumUtilities;

    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
