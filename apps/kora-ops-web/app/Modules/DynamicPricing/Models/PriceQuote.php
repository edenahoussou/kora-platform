<?php

namespace App\Modules\DynamicPricing\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $snapshot
 * @property Carbon|null $expires_at
 * @property string $coefficient
 * @property int $base_amount
 * @property int $final_amount
 */
class PriceQuote extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'departure_id',
        'origin_stop_id',
        'destination_stop_id',
        'fare_id',
        'pricing_rule_id',
        'base_amount',
        'coefficient',
        'final_amount',
        'passenger_count',
        'subtotal_amount',
        'group_discount_amount',
        'total_amount',
        'currency',
        'snapshot',
        'expires_at',
    ];

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    /**
     * @return BelongsTo<Fare, $this>
     */
    public function fare(): BelongsTo
    {
        return $this->belongsTo(Fare::class);
    }

    /**
     * @return BelongsTo<PricingRule, $this>
     */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'expires_at' => 'datetime',
            'coefficient' => 'decimal:4',
            'base_amount' => 'integer',
            'final_amount' => 'integer',
            'passenger_count' => 'integer',
            'subtotal_amount' => 'integer',
            'group_discount_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }
}
