<?php

namespace App\Modules\Booking\Models;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyQuoteLeg extends KoraModel
{
    protected $fillable = [
        'uuid', 'journey_quote_id', 'direction', 'leg_order', 'price_quote_id',
        'departure_id', 'origin_stop_id', 'destination_stop_id', 'departure_at',
        'arrival_at', 'amount',
    ];

    /** @return BelongsTo<JourneyQuote, $this> */
    public function journeyQuote(): BelongsTo
    {
        return $this->belongsTo(JourneyQuote::class);
    }

    /** @return BelongsTo<PriceQuote, $this> */
    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class);
    }

    /** @return BelongsTo<Departure, $this> */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /** @return BelongsTo<Stop, $this> */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /** @return BelongsTo<Stop, $this> */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    protected function casts(): array
    {
        return [
            'leg_order' => 'integer',
            'amount' => 'integer',
            'departure_at' => 'immutable_datetime',
            'arrival_at' => 'immutable_datetime',
        ];
    }
}
