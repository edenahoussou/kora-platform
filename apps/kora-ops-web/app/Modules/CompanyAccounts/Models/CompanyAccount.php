<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyDiscountType;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CompanyAccountStatus $status
 * @property CompanyAccountType $type
 * @property CompanyDiscountType|null $discount_type
 * @property string|null $discount_value
 * @property int $monthly_credit_limit
 */
class CompanyAccount extends KoraModel
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'reference',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'ifu',
        'rccm',
        'monthly_credit_limit',
        'discount_type',
        'discount_value',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyAccountType::class,
            'status' => CompanyAccountStatus::class,
            'monthly_credit_limit' => 'integer',
            'discount_type' => CompanyDiscountType::class,
            'discount_value' => 'decimal:2',
        ];
    }

    public function hasDiscount(): bool
    {
        return $this->discount_type !== null
            && $this->discount_value !== null
            && (float) $this->discount_value > 0;
    }

    /** @return HasMany<CompanyAccountMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CompanyAccountMember::class);
    }

    /** @return HasMany<CompanyTraveler, $this> */
    public function travelers(): HasMany
    {
        return $this->hasMany(CompanyTraveler::class);
    }

    /** @return HasMany<CompanyCreditHold, $this> */
    public function creditHolds(): HasMany
    {
        return $this->hasMany(CompanyCreditHold::class);
    }

    /** @return HasMany<CompanyCreditTransaction, $this> */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CompanyCreditTransaction::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function availableCredit(): int
    {
        return app(CompanyCreditService::class)->availableCredit($this);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
