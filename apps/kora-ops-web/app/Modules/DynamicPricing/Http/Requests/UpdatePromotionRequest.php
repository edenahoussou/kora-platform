<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdatePromotionRequest extends PromotionPayloadRequest
{
    protected function codeUniqueRule(): Unique
    {
        /** @var Promotion|null $promotion */
        $promotion = $this->route('promotion');

        return Rule::unique('promotions', 'code')
            ->whereNull('deleted_at')
            ->ignore($promotion?->getKey());
    }
}
