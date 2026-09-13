<?php

namespace App\Modules\Cash\Models;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property Carbon $business_date
 * @property CashSessionStatus $status
 * @property Carbon|null $opened_at
 * @property int $opening_float
 * @property int $total_cash
 * @property int $total_mobile_money
 */
class CashSession extends KoraModel
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    protected static function newFactory(): CashSessionFactory
    {
        return CashSessionFactory::new();
    }

    protected $fillable = [
        'uuid',
        'agent_id',
        'agency_id',
        'business_date',
        'opening_float',
        'total_cash',
        'total_mobile_money',
        'expected_balance',
        'actual_balance',
        'variance',
        'status',
        'closing_comment',
        'opened_at',
        'submitted_at',
        'closed_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CashLedger, $this>
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(CashLedger::class);
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
