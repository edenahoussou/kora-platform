<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StorePromotionRequest extends PromotionPayloadRequest
{
    protected function codeUniqueRule(): Unique
    {
        return Rule::unique('promotions', 'code')->whereNull('deleted_at');
    }
}
