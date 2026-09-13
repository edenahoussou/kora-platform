<?php

namespace App\Modules\Cash\Models;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\CashLedgerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $uuid
 * @property CashLedgerKind $kind
 * @property CashLedgerStatus $status
 * @property int $opening_balance
 * @property int $expected_balance
 * @property int|null $actual_balance
 * @property int|null $variance
 * @property int|null $inflows
 * @property int|null $outflows
 * @property int $movements_count
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 */
class CashLedger extends KoraModel implements HasMedia
{
    /** @use HasFactory<CashLedgerFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): CashLedgerFactory
    {
        return CashLedgerFactory::new();
    }

    protected $fillable = [
        'uuid',
        'cash_session_id',
        'ledger_key',
        'kind',
        'agency_payment_account_id',
        'opening_balance',
        'expected_balance',
        'actual_balance',
        'variance',
        'status',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'approved_by',
        'approved_at',
        'opening_comment',
        'closing_comment',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CashLedgerKind::class,
            'status' => CashLedgerStatus::class,
            'opening_balance' => 'integer',
            'expected_balance' => 'integer',
            'actual_balance' => 'integer',
            'variance' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return BelongsTo<AgencyPaymentAccount, $this>
     */
    public function agencyPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(AgencyPaymentAccount::class);
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('opening-evidence')
            ->useDisk('private');
        $this->addMediaCollection('closing-evidence')
            ->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued(); // @phpstan-ignore-line — nonQueued() est défini sur Conversion, pas sur le retour de fit()
        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1200, 1200)
            ->nonQueued(); // @phpstan-ignore-line — nonQueued() est défini sur Conversion, pas sur le retour de fit()
    }
}
