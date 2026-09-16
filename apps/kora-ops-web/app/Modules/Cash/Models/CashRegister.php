<?php

namespace App\Modules\Cash\Models;

use App\Models\User;
use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\CashRegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CashRegisterKind $kind
 * @property CashRegisterStatus $status
 */
class CashRegister extends KoraModel
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): CashRegisterFactory
    {
        return CashRegisterFactory::new();
    }

    protected $fillable = [
        'uuid',
        'agency_id',
        'code',
        'label',
        'kind',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CashRegisterKind::class,
            'status' => CashRegisterStatus::class,
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<CashSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cash_register_user')->withTimestamps();
    }

    public function isVault(): bool
    {
        return $this->kind === CashRegisterKind::Vault;
    }

    public function isActive(): bool
    {
        return $this->status === CashRegisterStatus::Active;
    }
}
