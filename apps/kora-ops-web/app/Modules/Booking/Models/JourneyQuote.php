<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property array<string, mixed> $snapshot
 */
class JourneyQuote extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid', 'user_id', 'trip_type', 'passenger_count', 'subtotal_amount',
        'dynamic_adjustment_amount', 'group_discount_amount', 'total_amount',
        'currency', 'snapshot', 'expires_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<JourneyQuoteLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(JourneyQuoteLeg::class)
            ->orderByRaw("CASE direction WHEN 'outbound' THEN 0 ELSE 1 END")
            ->orderBy('leg_order');
    }

    protected function casts(): array
    {
        return [
            'passenger_count' => 'integer',
            'subtotal_amount' => 'integer',
            'dynamic_adjustment_amount' => 'integer',
            'group_discount_amount' => 'integer',
            'total_amount' => 'integer',
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
