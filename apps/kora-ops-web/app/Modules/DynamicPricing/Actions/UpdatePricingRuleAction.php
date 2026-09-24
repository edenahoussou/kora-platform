<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Models\PricingRule;

class UpdatePricingRuleAction
{
    /**
     * @param  array{scope_type: string, line_id: int|null, origin_stop_id: int|null, destination_stop_id: int|null, direction: string, type: string, conditions: array<string, mixed>, percentage: float, priority: int, starts_at: string|null, ends_at: string|null}  $attributes
     */
    public function execute(PricingRule $rule, array $attributes): PricingRule
    {
        $rule->update($attributes);

        return $rule->refresh();
    }
}
