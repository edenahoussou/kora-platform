<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property array<string, mixed> $snapshot
 */
class ItineraryQuote extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'passenger_count',
        'subtotal_amount',
        'dynamic_adjustment_amount',
        'group_discount_amount',
        'promotion_id',
        'promotion_discount_amount',
        'total_amount',
        'currency',
        'snapshot',
        'expires_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ItineraryQuoteLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(ItineraryQuoteLeg::class)->orderBy('leg_order');
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    protected function casts(): array
    {
        return [
            'passenger_count' => 'integer',
            'subtotal_amount' => 'integer',
            'dynamic_adjustment_amount' => 'integer',
            'group_discount_amount' => 'integer',
            'promotion_discount_amount' => 'integer',
            'total_amount' => 'integer',
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
