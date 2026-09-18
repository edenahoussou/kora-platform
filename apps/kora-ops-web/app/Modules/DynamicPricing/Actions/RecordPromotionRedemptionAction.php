<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\DynamicPricing\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre l'usage réel d'une promotion lorsque la réservation passe à
 * l'état payée : une rédemption par (promotion, réservation), idempotente,
 * avec incrément atomique du compteur d'utilisations.
 */
class RecordPromotionRedemptionAction
{
    public function execute(Reservation $reservation): ?PromotionRedemption
    {
        $reservation->loadMissing('itineraryQuote.promotion');

        $quote = $reservation->itineraryQuote;
        $promotion = $quote?->promotion;

        if (! $quote || ! $promotion) {
            return null;
        }

        return DB::transaction(function () use ($reservation, $quote, $promotion): ?PromotionRedemption {
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
                'itinerary_quote_id' => $quote->getKey(),
                'discount_amount' => (int) $quote->promotion_discount_amount,
            ]);

            $lockedPromotion->increment('used_count');

            return $redemption;
        });
    }
}
