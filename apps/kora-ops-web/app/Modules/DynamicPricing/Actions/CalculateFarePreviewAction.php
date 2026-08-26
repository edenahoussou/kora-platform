<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;
use Carbon\CarbonInterface;

class CalculateFarePreviewAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(Line $line, Stop $originStop, Stop $destinationStop, CarbonInterface $travelDate): ?array
    {
        $fare = Fare::query()
            ->with('serviceClass')
            ->whereBelongsTo($line)
            ->whereBelongsTo($originStop, 'originStop')
            ->whereBelongsTo($destinationStop, 'destinationStop')
            ->where('status', FareStatus::Active->value)
            ->whereDate('effective_from', '<=', $travelDate)
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $travelDate))
            ->orderByDesc('effective_from')
            ->first();

        if (! $fare) {
            return null;
        }

        return [
            'fare_uuid' => $fare->uuid,
            'base_amount' => $fare->base_amount,
            'final_amount' => $fare->base_amount,
            'currency' => $fare->currency,
            'service_class_code' => $fare->serviceClass?->code,
            'service_class_name' => $fare->serviceClass?->name,
            'service_class_description' => $fare->serviceClass?->description,
            'is_quote' => false,
        ];
    }
}
