<?php

namespace App\Modules\Expenses\Models;

use App\Models\User;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property ExpenseStatus $status
 * @property ExpensePaymentChannel $payment_channel
 * @property int $amount
 * @property Carbon $occurred_at
 * @property int $agency_id
 * @property int $created_by
 * @property int|null $cash_ledger_id
 * @property string $currency
 * @property string $reference
 */
class Expense extends KoraModel implements HasMedia
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if (! $expense->reference) {
                $expense->reference = 'DEP-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
            }
        });
    }

    protected $fillable = [
        'uuid',
        'reference',
        'agency_id',
        'expense_category_id',
        'cash_session_id',
        'cash_ledger_id',
        'status',
        'payment_channel',
        'amount',
        'currency',
        'payee_label',
        'payee_reference',
        'description',
        'occurred_at',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'reversal_of_id',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_channel' => ExpensePaymentChannel::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<CashLedger, $this> */
    public function cashLedger(): BelongsTo
    {
        return $this->belongsTo(CashLedger::class);
    }

    /** @return HasOne<CashMovement, $this> */
    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
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

    /** @return BelongsTo<Expense, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence')->useDisk('private')->acceptsMimeTypes([
            'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
        ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media?->mime_type === 'application/pdf') {
            $this->addMediaConversion('preview')->pdfPageNumber(1)->nonQueued()->fit(Fit::Contain, 600, 800);
        }
    }
}
