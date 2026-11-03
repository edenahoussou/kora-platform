<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Wallet\Models\WalletRestitution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletRestitution
 */
class WalletRestitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'wallet_uuid' => $this->wallet?->uuid,
            'holder' => $this->whenLoaded('wallet', fn () => [
                'name' => $this->wallet->user?->name,
                'phone' => $this->wallet->user?->phone,
            ]),
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            'payout_reference' => $this->payout_reference,
            'payout_channel' => $this->payout_channel,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->name),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
