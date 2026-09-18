<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\DynamicPricing\Data\ResolvedPromotion;
use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Valide un code promo soumis au devis : la promotion doit être active,
 * dans sa période d'application, disposer encore d'usages et couvrir au
 * moins une jambe du devis pour le canal de vente concerné.
 */
class ResolvePromotionAction
{
    /**
     * @param  list<ItineraryLegQuoteRequest>  $legs
     *
     * @throws ValidationException
     */
    public function execute(string $code, array $legs, ?ReservationChannel $channel): ResolvedPromotion
    {
        $promotion = Promotion::query()
            ->where('code', Str::upper(trim($code)))
            ->first();

        $fail = fn (string $message): ValidationException => ValidationException::withMessages([
            'promo_code' => $message,
        ]);

        if (! $promotion) {
            throw $fail('Ce code promotionnel est inconnu.');
        }

        if (! $promotion->isEffective()) {
            throw $fail('Ce code promotionnel n’est pas applicable actuellement.');
        }

        if (! $promotion->hasUsesLeft()) {
            throw $fail('Ce code promotionnel a atteint sa limite d’utilisation.');
        }

        $eligibleLegIndexes = [];
        foreach ($legs as $index => $leg) {
            if ($promotion->coversLeg(
                lineId: (int) $leg->departure->line_id,
                originStopId: (int) $leg->originStop->getKey(),
                destinationStopId: (int) $leg->destinationStop->getKey(),
                channel: $channel,
            )) {
                $eligibleLegIndexes[] = $index;
            }
        }

        if ($eligibleLegIndexes === []) {
            throw $fail('Ce code promotionnel ne s’applique pas à cet itinéraire.');
        }

        return new ResolvedPromotion($promotion, $eligibleLegIndexes);
    }
}
