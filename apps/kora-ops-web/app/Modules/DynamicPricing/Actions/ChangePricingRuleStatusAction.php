<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Models\PricingRule;

class ChangePricingRuleStatusAction
{
    public function execute(PricingRule $rule, PricingRuleStatus $status): PricingRule
    {
        $rule->update(['status' => $status]);

        return $rule->refresh();
    }
}
