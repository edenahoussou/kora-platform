<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Models\Fare;

class UpdateFareAction
{
    /**
     * @param  array{base_amount: int, currency: string, effective_from: string, effective_until: string|null}  $attributes
     */
    public function execute(Fare $fare, array $attributes): Fare
    {
        $fare->update($attributes);

        return $fare->refresh();
    }
}
