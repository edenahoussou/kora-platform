<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\DynamicPricing\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre l'usage réel d'une promotion lorsque la réservation passe à
 * l'état payée : une rédemption par (promotion, réservation), idempotente,
 * avec incrément atomique du compteur d'utilisations.
 *
 * La promotion effective est celle portée par la réservation — appliquée au
 * devis ou directement sur la réservation avant paiement ; la lecture du devis
 * reste un repli pour les réservations antérieures à cette colonne.
 */
class RecordPromotionRedemptionAction
{
    public function execute(Reservation $reservation): ?PromotionRedemption
    {
        $reservation->loadMissing('promotion', 'itineraryQuote.promotion');

        /** @var ItineraryQuote|null $quote */
        $quote = $reservation->itineraryQuote;
        $promotion = $reservation->promotion ?? $quote?->promotion;

        if (! $promotion) {
            return null;
        }

        $discountAmount = $reservation->promotion_id !== null
            ? (int) $reservation->promotion_discount_amount
            : (int) $quote->promotion_discount_amount;

        return DB::transaction(function () use ($reservation, $promotion, $discountAmount): ?PromotionRedemption {
            $existing = PromotionRedemption::query()
                ->where('promotion_id', $promotion->getKey())
                ->where('reservation_id', $reservation->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            $lockedPromotion = Promotion::query()
                ->whereKey($promotion->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedPromotion?->hasUsesLeft()) {
                return null;
            }

            $redemption = PromotionRedemption::query()->create([
                'promotion_id' => $lockedPromotion->getKey(),
                'reservation_id' => $reservation->getKey(),
                'itinerary_quote_id' => $reservation->itinerary_quote_id,
                'discount_amount' => $discountAmount,
            ]);

            $lockedPromotion->increment('used_count');

            return $redemption;
        });
    }
}
