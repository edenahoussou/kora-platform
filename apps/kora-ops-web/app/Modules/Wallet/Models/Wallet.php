<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $cash_balance
 * @property int $points_balance
 * @property int $points_compensation_due
 * @property Carbon|null $points_expires_at
 * @property string $referral_code
 * @property int|null $referred_by_user_id
 */
class Wallet extends KoraModel
{
    protected $fillable = [
        'uuid',
        'user_id',
        'cash_balance',
        'points_balance',
        'points_expires_at',
        'points_compensation_due',
        'referral_code',
        'referred_by_user_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** @return HasMany<WalletHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(WalletHold::class);
    }

    /** @return HasMany<WalletTopUp, $this> */
    public function topups(): HasMany
    {
        return $this->hasMany(WalletTopUp::class);
    }

    /** @return HasMany<WalletRestitution, $this> */
    public function restitutions(): HasMany
    {
        return $this->hasMany(WalletRestitution::class);
    }

    /** @return HasMany<WalletUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(WalletUsage::class);
    }

    /** @return HasMany<WalletBonusGrant, $this> */
    public function bonusGrants(): HasMany
    {
        return $this->hasMany(WalletBonusGrant::class);
    }

    protected function casts(): array
    {
        return [
            'cash_balance' => 'integer',
            'points_balance' => 'integer',
            'points_compensation_due' => 'integer',
            'points_expires_at' => 'datetime',
        ];
    }
}
