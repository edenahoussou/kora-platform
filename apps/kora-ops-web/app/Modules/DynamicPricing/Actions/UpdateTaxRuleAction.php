<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Models\TaxRule;

use function Illuminate\Support\enum_value;

class UpdateTaxRuleAction
{
    /**
     * @param  array{service_type: string, scope: string, tax_code: string, tax_rate: float, starts_on: string, ends_on: string|null, status: string}  $attributes
     */
    public function execute(TaxRule $rule, array $attributes): TaxRule
    {
        $becomesActive = $attributes['status'] === TaxRuleStatus::Active->value
            && enum_value($rule->status) !== TaxRuleStatus::Active->value;

        $rule->update([
            ...$attributes,
            'validated_by' => $becomesActive ? auth()->id() : $rule->validated_by,
        ]);

        return $rule->refresh();
    }
}
