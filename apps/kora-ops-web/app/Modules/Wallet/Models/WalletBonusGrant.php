<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\LoyaltyBonusKind;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unicité d'attribution d'un bonus par compte et par période.
 *
 * @property int $wallet_id
 * @property LoyaltyBonusKind $kind
 * @property string $period_key
 */
class WalletBonusGrant extends KoraModel
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'kind',
        'period_key',
        'transaction_id',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<WalletTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => LoyaltyBonusKind::class,
        ];
    }
}
