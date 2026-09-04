<?php

namespace App\Modules\DynamicPricing\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\DynamicPricing\Data\GroupDiscountRuleStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property GroupDiscountRuleStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class GroupDiscountRule extends KoraModel
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'status', 'passenger_threshold', 'percentage', 'starts_at', 'ends_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupDiscountRuleStatus::class,
            'passenger_threshold' => 'integer',
            'percentage' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
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

    public function isEffective(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === GroupDiscountRuleStatus::Active
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gte($at));
    }
}
