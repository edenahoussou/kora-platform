<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PromotionStatus;
use App\Modules\DynamicPricing\Models\Promotion;

class CreatePromotionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Promotion
    {
        return Promotion::query()->create([
            ...$attributes,
            'status' => PromotionStatus::Draft->value,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }
}
