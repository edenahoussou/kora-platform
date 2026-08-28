<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Models\TaxRule;

use function Illuminate\Support\enum_value;

class ChangeTaxRuleStatusAction
{
    public function execute(TaxRule $rule, TaxRuleStatus $status): TaxRule
    {
        $becomesActive = $status === TaxRuleStatus::Active
            && enum_value($rule->status) !== TaxRuleStatus::Active->value;

        $rule->update([
            'status' => $status,
            'validated_by' => $becomesActive ? auth()->id() : $rule->validated_by,
        ]);

        return $rule->refresh();
    }
}
