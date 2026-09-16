<?php

namespace App\Modules\Cash\Models;

use App\Models\User;
use App\Modules\Cash\Data\CashTransferStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CashTransferStatus $status */
class CashTransfer extends KoraModel
{
    protected $fillable = [
        'uuid',
        'agency_id',
        'source_cash_register_id',
        'destination_cash_register_id',
        'destination_type',
        'amount',
        'currency',
        'status',
        'reference',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashTransferStatus::class,
            'amount' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function sourceCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'source_cash_register_id');
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function destinationCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'destination_cash_register_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
