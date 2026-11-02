<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property AgencyStatus $status */
class Agency extends KoraModel
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): AgencyFactory
    {
        return AgencyFactory::new();
    }

    protected $attributes = ['kind' => 'internal'];

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'city',
        'department',
        'country',
        'head_user_id',
        'status',
        'kind',
    ];

    protected function casts(): array
    {
        return ['status' => AgencyStatus::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return HasMany<AgencyPaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(AgencyPaymentAccount::class);
    }

    /** @return HasMany<CashRegister, $this> */
    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }
}
