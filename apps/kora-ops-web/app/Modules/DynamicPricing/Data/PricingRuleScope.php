<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PricingRuleScope: string
{
    use HasEnumUtilities;

    case Network = 'network';
    case Line = 'line';
    case Segment = 'segment';
}
