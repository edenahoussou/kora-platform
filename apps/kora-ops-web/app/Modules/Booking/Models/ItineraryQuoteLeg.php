<?php

namespace App\Modules\Booking\Models;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryQuoteLeg extends KoraModel
{
    protected $fillable = [
        'itinerary_quote_id',
        'direction',
        'leg_order',
        'price_quote_id',
        'departure_id',
        'origin_stop_id',
        'destination_stop_id',
        'amount',
    ];

    /** @return BelongsTo<ItineraryQuote, $this> */
    public function itineraryQuote(): BelongsTo
    {
        return $this->belongsTo(ItineraryQuote::class);
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
        ];
    }
}
