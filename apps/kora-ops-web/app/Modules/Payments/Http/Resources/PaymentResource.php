<?php

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'expires_at' => $this->reservation?->expires_at?->toIso8601String(),
            'polling_interval_seconds' => 10,
            'reservation' => $this->whenLoaded('reservation', fn () => [
                'uuid' => $this->reservation->uuid,
                'status' => $this->reservation->status->value,
                'departure_uuid' => $this->reservation->departure?->uuid,
            ]),
        ];
    }
}
