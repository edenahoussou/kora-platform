<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\PromotionStatus;
use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Validation\ValidationException;

class ChangePromotionStatusAction
{
    /**
     * Cycle de vie commercial : draft → active ⇄ inactive → archived.
     * « Planifiée » et « expirée » sont des états dérivés de la période,
     * jamais des transitions.
     *
     * @throws ValidationException
     */
    public function execute(Promotion $promotion, PromotionStatus $status): Promotion
    {
        $allowed = match ($promotion->status) {
            PromotionStatus::Draft => [PromotionStatus::Active, PromotionStatus::Archived],
            PromotionStatus::Active => [PromotionStatus::Inactive, PromotionStatus::Archived],
            PromotionStatus::Inactive => [PromotionStatus::Active, PromotionStatus::Archived],
            PromotionStatus::Archived => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Transition de statut non autorisée pour cette promotion.',
            ]);
        }

        $promotion->update([
            'status' => $status,
            'updated_by' => auth()->id(),
        ]);

        return $promotion->refresh();
    }
}
