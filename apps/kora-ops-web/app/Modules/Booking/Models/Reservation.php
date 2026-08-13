<?php

namespace App\Modules\Booking\Models;

use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'departure_id',
        'price_quote_id',
        'status',
        'total_amount',
        'currency',
        'channel',
        'expires_at',
        'total_snapshot',
    ];

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<PriceQuote, $this>
     */
    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class);
    }

    /**
     * @return HasMany<ReservationSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(ReservationSegment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'channel' => ReservationChannel::class,
            'expires_at' => 'datetime',
            'total_snapshot' => 'array',
        ];
    }
}
