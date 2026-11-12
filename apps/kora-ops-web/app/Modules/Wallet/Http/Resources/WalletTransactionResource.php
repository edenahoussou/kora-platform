<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletTransaction
 */
class WalletTransactionResource extends JsonResource
{
    /**
     * Expose une écriture du ledger Wallet.
     *
     * @param  Request  $request  Requête courante
     * @return array<string, mixed> Représentation API
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'balance_type' => $this->balance_type->value,
            'direction' => $this->direction->value,
            'amount' => (int) $this->amount,
            'reason' => $this->reason->value,
            'reference' => [
                'type' => $this->reference_type,
                'uuid' => $this->reference?->getAttribute('uuid'),
            ],
            'meta' => $this->meta === null ? null : array_intersect_key($this->meta, array_flip([
                'reservation_uuid', 'payment_uuid', 'departure_uuid', 'leg_order', 'bonus_kind', 'expires_at',
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
