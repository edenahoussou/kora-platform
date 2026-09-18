<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PromotionStatus;
use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Validation\ValidationException;

class UpdatePromotionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function execute(Promotion $promotion, array $attributes): Promotion
    {
        if ($promotion->status === PromotionStatus::Archived) {
            throw ValidationException::withMessages([
                'promotion' => 'Une promotion archivée ne peut plus être modifiée.',
            ]);
        }

        $promotion->update([
            ...$attributes,
            'updated_by' => auth()->id(),
        ]);

        return $promotion->refresh();
    }
}
