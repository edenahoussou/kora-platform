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

    /**
     * Ensure float values like 1.0 are serialized as "1.0" (not "1")
     * so that JSON consumers receive a float, not an integer.
     */
    public function jsonOptions(): int
    {
        return JSON_PRESERVE_ZERO_FRACTION;
    }
}
