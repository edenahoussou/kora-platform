<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Models\PricingRule;

class StorePricingRuleRequest extends PricingRulePayloadRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PricingRule::class) ?? false;
    }
}
