<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PricingRuleType: string
{
    use HasEnumUtilities;

    case Increase = 'increase';
    case Decrease = 'decrease';
}
