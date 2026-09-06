<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;

class CalculateFarePreviewAction
{
    public function __construct(private CalculateDynamicPriceAction $calculateDynamicPrice) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop): ?array
    {
        $price = $this->calculateDynamicPrice->handle($departure, $originStop, $destinationStop);
        if (! $price) {
            return null;
        }

        return [
            'fare_uuid' => $price->fare->uuid,
            'base_amount' => $price->baseAmount,
            'final_amount' => $price->finalAmount,
            'coefficient' => $price->coefficient,
            'dynamic_adjustment_amount' => $price->adjustmentAmount(),
            'pricing_rule_uuid' => $price->rule?->uuid,
            'fill_rate' => $price->fillRate,
            'hours_before_departure' => $price->hoursBeforeDeparture,
            'currency' => $price->fare->currency,
            'service_class_code' => $price->fare->serviceClass?->code,
            'service_class_name' => $price->fare->serviceClass?->name,
            'service_class_description' => $price->fare->serviceClass?->description,
            'is_quote' => false,
        ];
    }
}
