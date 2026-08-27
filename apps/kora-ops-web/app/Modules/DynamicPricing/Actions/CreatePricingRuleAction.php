<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Models\PricingRule;

class CreatePricingRuleAction
{
    /**
     * @param  array{line_id: int|null, type: string, conditions: array<string, mixed>, percentage: float, priority: int, starts_at: string|null, ends_at: string|null}  $attributes
     */
    public function execute(array $attributes): PricingRule
    {
        return PricingRule::query()->create([
            ...$attributes,
            'status' => PricingRuleStatus::Draft->value,
            'created_by' => auth()->id(),
        ]);
    }
}
