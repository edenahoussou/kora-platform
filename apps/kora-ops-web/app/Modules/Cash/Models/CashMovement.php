<?php

namespace App\Modules\Cash\Models;

use App\Models\User;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property CashMovementDirection $direction
 * @property CashMovementStatus $status
 * @property int $amount
 * @property Carbon|null $occurred_at
 */
class CashMovement extends KoraModel implements HasMedia
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): CashMovementFactory
    {
        return CashMovementFactory::new();
    }

    protected $fillable = [
        'uuid',
        'cash_session_id',
        'cash_ledger_id',
        'source_type',
        'source_id',
        'type',
        'direction',
        'status',
        'amount',
        'currency',
        'description',
        'reference',
        'destination_label',
        'destination_reference',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return BelongsTo<CashLedger, $this>
     */
    public function cashLedger(): BelongsTo
    {
        return $this->belongsTo(CashLedger::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'direction' => CashMovementDirection::class,
            'status' => CashMovementStatus::class,
            'amount' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('transfer-evidence')->useDisk('private');
        $this->addMediaCollection('adjustment-evidence')->useDisk('private');
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
