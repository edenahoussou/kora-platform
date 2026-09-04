<?php

namespace App\Modules\DynamicPricing\Data;

enum GroupDiscountRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
