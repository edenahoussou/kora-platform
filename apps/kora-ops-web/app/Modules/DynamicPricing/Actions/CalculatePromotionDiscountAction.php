<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PromotionBenefitType;
use App\Modules\DynamicPricing\Data\ResolvedPromotion;

/**
 * Calcule la remise promotion en FCFA à partir des montants unitaires des
 * jambes : un pourcentage se décline sur le sous-total des seules jambes
 * couvertes par le périmètre ; un montant fixe se déduit une fois du total.
 */
class CalculatePromotionDiscountAction
{
    /**
     * @param  list<int>  $legUnitAmounts  Montant final par passager de chaque jambe.
     */
    public function execute(ResolvedPromotion $resolved, array $legUnitAmounts, int $passengerCount): int
    {
        $subtotal = array_sum($legUnitAmounts) * $passengerCount;

        if ($resolved->promotion->benefit_type === PromotionBenefitType::Percentage) {
            $eligibleSubtotal = array_sum(array_map(
                fn (int $index): int => $legUnitAmounts[$index] ?? 0,
                $resolved->eligibleLegIndexes,
            )) * $passengerCount;

            return min((int) round($eligibleSubtotal * (float) $resolved->promotion->benefit_value / 100), $subtotal);
        }

        return min((int) round((float) $resolved->promotion->benefit_value), $subtotal);
    }
}
