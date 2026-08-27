<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Models\PricingSetting;

class UpdatePricingSettingAction
{
    /** @param array{floor_coefficient: float|null, ceiling_coefficient: float|null} $attributes */
    public function execute(array $attributes, int $userId): PricingSetting
    {
        $setting = PricingSetting::query()->first();

        if ($setting) {
            $setting->update([...$attributes, 'updated_by' => $userId]);

            return $setting->refresh();
        }

        return PricingSetting::query()->create([...$attributes, 'updated_by' => $userId]);
    }
}
