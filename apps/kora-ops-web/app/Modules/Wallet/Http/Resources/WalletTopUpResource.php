<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletTopUp
 */
class WalletTopUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            'payment' => [
                'uuid' => $this->payment?->uuid,
                'status' => $this->payment?->status->value,
                'provider' => $this->payment?->provider,
                'provider_reference' => $this->payment?->provider_reference,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
