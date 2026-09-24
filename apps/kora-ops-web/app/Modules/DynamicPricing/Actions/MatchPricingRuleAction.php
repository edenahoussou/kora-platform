<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Models\PricingRule;

class MatchPricingRuleAction
{
    /**
     * Sélectionne la règle de pricing active dont le périmètre (réseau, ligne
     * ou segment O/D), la direction et les conditions (remplissage, délai)
     * matchent réellement le contexte de la demande, par ordre de priorité
     * croissante (1 = plus prioritaire). Une règle sans "conditions"
     * s'applique inconditionnellement dès lors qu'elle est active et dans sa
     * période de validité.
     */
    public function handle(Departure $departure, Stop $originStop, Stop $destinationStop, float $fillRate, float $hoursBeforeDeparture): ?PricingRule
    {
        $candidates = PricingRule::query()
            ->where(fn ($query) => $query->where('line_id', $departure->line_id)->orWhereNull('line_id'))
            ->where('status', PricingRuleStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('priority')
            ->get();

        foreach ($candidates as $candidate) {
            if (! $candidate->coversJourney($departure->line_id, $originStop, $destinationStop)) {
                continue;
            }
            if (! $candidate->matchesDirection($originStop, $destinationStop)) {
                continue;
            }
            if (! $this->conditionsMatch($candidate->conditions ?? [], $fillRate, $hoursBeforeDeparture)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function conditionsMatch(array $conditions, float $fillRate, float $hoursBeforeDeparture): bool
    {
        $fillRateMin = $conditions['fill_rate_min'] ?? null;
        if ($fillRateMin !== null && $fillRate < (float) $fillRateMin) {
            return false;
        }

        $fillRateMax = $conditions['fill_rate_max'] ?? null;
        if ($fillRateMax !== null && $fillRate > (float) $fillRateMax) {
            return false;
        }

        $hoursMin = $conditions['hours_before_departure_min'] ?? null;
        if ($hoursMin !== null && $hoursBeforeDeparture < (float) $hoursMin) {
            return false;
        }

        $hoursMax = $conditions['hours_before_departure_max'] ?? null;
        if ($hoursMax !== null && $hoursBeforeDeparture > (float) $hoursMax) {
            return false;
        }

        return true;
    }
}
