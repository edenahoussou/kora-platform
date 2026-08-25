<?php

namespace App\Modules\DynamicPricing\Http\Resources;

use App\Modules\DynamicPricing\Models\PriceQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceQuote
 */
class PriceQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $passengerCount = $this->snapshot['passenger_count'] ?? 1;
        $totalAmount = $this->snapshot['total_amount'] ?? ($this->final_amount * $passengerCount);

        return [
            'uuid' => $this->uuid,
            'departure_uuid' => $this->departure?->uuid,
            'origin_stop_uuid' => $this->originStop?->uuid,
            'destination_stop_uuid' => $this->destinationStop?->uuid,
            'service_class_uuid' => $this->fare?->serviceClass?->uuid,
            'base_amount' => $this->base_amount,
            'coefficient' => (float) $this->coefficient,
            'final_amount' => $this->final_amount,
            'currency' => $this->currency,
            'passenger_count' => $passengerCount,
            'total_amount' => $totalAmount,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'snapshot' => $this->snapshot,
        ];
    }
}
