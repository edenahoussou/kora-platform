<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Models\TaxRule;

class CreateTaxRuleAction
{
    /**
     * @param  array{service_type: string, scope: string, tax_code: string, tax_rate: float, starts_on: string, ends_on: string|null, status: string}  $attributes
     */
    public function execute(array $attributes): TaxRule
    {
        return TaxRule::query()->create([
            ...$attributes,
            // validated_by n'est renseigné que lorsque le taux est directement créé actif
            // (confirmé DGI) — jamais depuis l'input client, pour éviter toute usurpation.
            'validated_by' => $attributes['status'] === TaxRuleStatus::Active->value ? auth()->id() : null,
        ]);
    }
}
