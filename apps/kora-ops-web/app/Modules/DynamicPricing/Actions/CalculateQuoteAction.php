<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CalculateQuoteAction
{
    public function __construct(
        private ResolveDepartureSellabilityAction $resolveSellability,
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private GroupPricingService $groupPricing,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(
        Departure $departure,
        Stop $originStop,
        Stop $destinationStop,
        ?ServiceClass $serviceClass = null,
        int $passengerCount = 1,
    ): PriceQuote {
        $sellability = $this->resolveSellability->handle($departure);
        if (! $sellability->isSellable) {
            throw ValidationException::withMessages([
                'departure_uuid' => 'La vente est fermée pour ce départ.',
            ]);
        }

        $departure->loadMissing(['line.stops']);

        if ($originStop->line_id !== $departure->line_id || $destinationStop->line_id !== $departure->line_id) {
            throw ValidationException::withMessages([
                'origin_stop_uuid' => 'Les arrêts spécifiés n\'appartiennent pas à la ligne de ce départ.',
            ]);
        }

        if ($originStop->stop_order >= $destinationStop->stop_order) {
            throw ValidationException::withMessages([
                'destination_stop_uuid' => 'L\'arrêt de destination doit être situé après l\'arrêt d\'origine.',
            ]);
        }

        $fareQuery = Fare::query()
            ->with(['serviceClass'])
            ->whereBelongsTo($departure->line)
            ->whereBelongsTo($originStop, 'originStop')
            ->whereBelongsTo($destinationStop, 'destinationStop')
            ->where('status', FareStatus::Active->value)
            ->whereDate('effective_from', '<=', $departure->departure_date)
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $departure->departure_date));

        if ($serviceClass) {
            $fareQuery->where('service_class_id', $serviceClass->id);
        }

        $fare = $fareQuery->orderByDesc('effective_from')->first();

        if (! $fare) {
            throw ValidationException::withMessages([
                'origin_stop_uuid' => 'Aucun tarif actif n\'a été trouvé pour ce trajet.',
            ]);
        }

        [$fillRate, $hoursBeforeDeparture] = $this->measureConditionInputs($departure, $originStop, $destinationStop);

        $rule = $this->matchPricingRule($departure, $fillRate, $hoursBeforeDeparture);

        $coefficient = 1.0;
        if ($rule) {
            $percentage = (float) $rule->percentage;
            if ($rule->type === PricingRuleType::Increase) {
                $coefficient = round(1.0 + ($percentage / 100), 4);
            } elseif ($rule->type === PricingRuleType::Decrease) {
                $coefficient = round(max(0.0, 1.0 - ($percentage / 100)), 4);
            }
        }

        $coefficient = $this->applyFloorAndCeiling($coefficient);

        $baseAmount = (int) $fare->base_amount;
        $finalAmount = (int) round($baseAmount * $coefficient);
        $pricing = $this->groupPricing->calculate(
            unitAmounts: [$finalAmount],
            passengerCount: $passengerCount,
            currency: $fare->currency,
            dynamicAdjustments: [$finalAmount - $baseAmount],
        );

        $snapshot = [
            'fare_uuid' => $fare->uuid,
            'line_code' => $departure->line->code,
            'origin_stop' => $originStop->name,
            'destination_stop' => $destinationStop->name,
            'service_class' => $fare->serviceClass->name ?? 'Standard',
            'passenger_count' => $passengerCount,
            'subtotal_amount' => $pricing['subtotal_amount'],
            'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
            'group' => $pricing['group'],
            'group_discount' => $pricing['group_discount'],
            'passenger_allocations' => $pricing['passenger_allocations'],
            'total_amount' => $pricing['total_amount'],
            // Traçabilité de la décision de tarification dynamique — utile en support/litige,
            // même si ces champs ne sont pas exposés tels quels sur le contrat public de devis.
            'pricing_rule_uuid' => $rule?->uuid,
            'fill_rate_at_quote' => $fillRate,
            'hours_before_departure_at_quote' => $hoursBeforeDeparture,
        ];

        return PriceQuote::create([
            'departure_id' => $departure->id,
            'origin_stop_id' => $originStop->id,
            'destination_stop_id' => $destinationStop->id,
            'fare_id' => $fare->id,
            'pricing_rule_id' => $rule?->id,
            'base_amount' => $baseAmount,
            'coefficient' => $coefficient,
            'final_amount' => $finalAmount,
            'passenger_count' => $passengerCount,
            'subtotal_amount' => $pricing['subtotal_amount'],
            'group_discount_amount' => $pricing['group_discount']['amount'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $fare->currency,
            'snapshot' => $snapshot,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * Mesure les deux entrées utilisées par les conditions des règles de pricing
     * dynamique (CDC §3.1.3.4) : taux de remplissage du segment O/D demandé
     * (en %, sur les sièges vendables) et délai avant départ (en heures).
     *
     * @return array{0: float, 1: float}
     */
    private function measureConditionInputs(Departure $departure, Stop $originStop, Stop $destinationStop): array
    {
        $seats = $this->buildSeatAvailability->handle($departure, $originStop, $destinationStop);

        $sellableSeats = $seats->filter(fn ($seat) => $seat->availabilityStatus !== SeatAvailabilityStatus::Blocked);
        $sellableCount = $sellableSeats->count();

        $fillRate = $sellableCount > 0
            ? round(
                $sellableSeats
                    ->whereIn('availabilityStatus', [SeatAvailabilityStatus::Held, SeatAvailabilityStatus::Occupied])
                    ->count() / $sellableCount * 100,
                2,
            )
            : 0.0;

        $scheduledAt = $departure->scheduledDepartureAt();
        // Valeur absolue + signe explicite plutôt que de compter sur le sens
        // conventionnel de diffInHours($date, false), pour éviter toute ambiguïté.
        $hoursBeforeDeparture = round(
            Carbon::now()->diffInHours($scheduledAt, true) * ($scheduledAt->isFuture() ? 1 : -1),
            2,
        );

        return [$fillRate, $hoursBeforeDeparture];
    }

    /**
     * Sélectionne la règle de pricing active dont les conditions (remplissage,
     * délai) matchent réellement le contexte de la demande, par ordre de
     * priorité croissante (1 = plus prioritaire). Une règle sans "conditions"
     * s'applique inconditionnellement dès lors qu'elle est active et dans sa
     * période de validité.
     */
    private function matchPricingRule(Departure $departure, float $fillRate, float $hoursBeforeDeparture): ?PricingRule
    {
        $candidates = PricingRule::query()
            ->where(fn ($query) => $query->where('line_id', $departure->line_id)->orWhereNull('line_id'))
            ->where('status', PricingRuleStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('priority')
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->conditionsMatch($candidate->conditions ?? [], $fillRate, $hoursBeforeDeparture)) {
                return $candidate;
            }
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

    /**
     * Applique le plancher/plafond global paramétrable par la Direction
     * Commerciale (CDC MVP §9.2). Aucun réglage enregistré = aucun bornage.
     */
    private function applyFloorAndCeiling(float $coefficient): float
    {
        $settings = PricingSetting::current();
        if (! $settings) {
            return $coefficient;
        }

        if ($settings->floor_coefficient !== null) {
            $coefficient = max($coefficient, (float) $settings->floor_coefficient);
        }

        if ($settings->ceiling_coefficient !== null) {
            $coefficient = min($coefficient, (float) $settings->ceiling_coefficient);
        }

        return round($coefficient, 4);
    }
}
