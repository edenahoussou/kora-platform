<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usage réel d'une promotion : enregistré uniquement lorsque la vente est
 * confirmée (réservation payée) — jamais au devis ni au blocage abandonné.
 */
class PromotionRedemption extends KoraModel
{
    protected $fillable = [
        'uuid',
        'promotion_id',
        'reservation_id',
        'itinerary_quote_id',
        'discount_amount',
    ];

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<ItineraryQuote, $this> */
    public function itineraryQuote(): BelongsTo
    {
        return $this->belongsTo(ItineraryQuote::class);
    }
}
