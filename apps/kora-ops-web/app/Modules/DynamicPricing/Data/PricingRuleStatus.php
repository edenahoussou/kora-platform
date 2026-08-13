<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PricingRuleStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';
}
