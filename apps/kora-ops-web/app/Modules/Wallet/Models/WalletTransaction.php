<?php

namespace App\Modules\Wallet\Models;

use App\Modules\IdentityAccess\Models\Agency;
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
 * @property int|null $loyalty_rule_version_id
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
        'loyalty_rule_version_id',
        'meta',
    ];

    /**
     * Wallet mouvementé.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Version de règle de fidélité active au moment de l'écriture, si elle existe.
     *
     * @return BelongsTo<WalletLoyaltyRuleVersion, $this>
     */
    public function loyaltyRuleVersion(): BelongsTo
    {
        return $this->belongsTo(WalletLoyaltyRuleVersion::class);
    }

    /**
     * Objet métier à l'origine de l'écriture.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Montant signé selon le sens de l'écriture.
     *
     * @return int Montant positif en crédit, négatif en débit
     */
    public function signedAmount(): int
    {
        return $this->direction === WalletDirection::Credit ? $this->amount : -$this->amount;
    }

    /**
     * Casts des enums et du payload.
     *
     * @return array<string, string>
     */
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
