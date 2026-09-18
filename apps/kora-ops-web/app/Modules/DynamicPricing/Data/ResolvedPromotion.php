<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\DynamicPricing\Models\Promotion;

/**
 * Résultat de la résolution d'un code promo : la promotion applicable et
 * les index des jambes du devis couvertes par son périmètre.
 */
final class ResolvedPromotion
{
    /**
     * @param  list<int>  $eligibleLegIndexes
     */
    public function __construct(
        public readonly Promotion $promotion,
        public readonly array $eligibleLegIndexes,
    ) {}
}
