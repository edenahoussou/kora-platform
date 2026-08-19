<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;
use Illuminate\Support\Str;

class CreateFareAction
{
    /**
     * @param  array{line_id: int, service_class_id: int, origin_stop_id: int, destination_stop_id: int, base_amount: int, currency: string, effective_from: string, effective_until: string|null}  $attributes
     */
    public function execute(array $attributes): Fare
    {
        return Fare::query()->create([
            ...$attributes,
            'uuid' => Str::uuid()->toString(),
            'status' => FareStatus::Draft->value,
            'created_by' => auth()->id(),
        ]);
    }
}
