<?php

namespace App\Modules\DynamicPricing\Http\Requests;

class UpdatePricingRuleRequest extends PricingRulePayloadRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('pricingRule')) ?? false;
    }
}
