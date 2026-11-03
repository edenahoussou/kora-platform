<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletDirection;
use App\Modules\Wallet\Data\WalletTransactionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Écriture append-only du ledger Wallet.
 *
 * @property int $wallet_id
 * @property WalletBalanceType $balance_type
 * @property WalletDirection $direction
 * @property int $amount
 * @property WalletTransactionReason $reason
 * @property string $idempotency_key
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class WalletTransaction extends KoraModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'balance_type',
        'direction',
        'amount',
        'reason',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'actor_user_id',
        'agency_id',
        'meta',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedAmount(): int
    {
        return $this->direction === WalletDirection::Credit ? $this->amount : -$this->amount;
    }

    protected function casts(): array
    {
        return [
            'balance_type' => WalletBalanceType::class,
            'direction' => WalletDirection::class,
            'reason' => WalletTransactionReason::class,
            'amount' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
