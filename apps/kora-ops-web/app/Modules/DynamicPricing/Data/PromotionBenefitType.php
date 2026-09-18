<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PromotionBenefitType: string
{
    use HasEnumUtilities;

    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
