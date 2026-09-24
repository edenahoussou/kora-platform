<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\DynamicPriceResult;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\SeatInventory\Actions\BuildSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\SeatAvailabilityStatus;
use Illuminate\Support\Carbon;

class CalculateDynamicPriceAction
{
    public function __construct(
        private BuildSeatAvailabilityAction $buildSeatAvailability,
        private MatchPricingRuleAction $matchPricingRule,
    ) {}

    public function handle(
        Departure $departure,
        Stop $originStop,
        Stop $destinationStop,
        ?ServiceClass $serviceClass = null,
    ): ?DynamicPriceResult {
        $departure->loadMissing('line');

        $fareQuery = Fare::query()
            ->with('serviceClass')
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
            return null;
        }

        [$fillRate, $hoursBeforeDeparture] = $this->measureConditionInputs($departure, $originStop, $destinationStop);
        $rule = $this->matchPricingRule->handle($departure, $originStop, $destinationStop, $fillRate, $hoursBeforeDeparture);
        $coefficient = $this->coefficientFor($rule);
        $coefficient = $this->applyFloorAndCeiling($coefficient);
        $baseAmount = (int) $fare->base_amount;

        return new DynamicPriceResult(
            fare: $fare,
            rule: $rule,
            fillRate: $fillRate,
            hoursBeforeDeparture: $hoursBeforeDeparture,
            coefficient: $coefficient,
            baseAmount: $baseAmount,
            finalAmount: (int) round($baseAmount * $coefficient),
        );
    }

    /** @return array{0: float, 1: float} */
    private function measureConditionInputs(Departure $departure, Stop $originStop, Stop $destinationStop): array
    {
        $seats = $this->buildSeatAvailability->handle($departure, $originStop, $destinationStop);
        $sellableSeats = $seats->filter(fn ($seat) => $seat->availabilityStatus !== SeatAvailabilityStatus::Blocked);
        $sellableCount = $sellableSeats->count();

        $fillRate = $sellableCount > 0
            ? round($sellableSeats->whereIn('availabilityStatus', [SeatAvailabilityStatus::Held, SeatAvailabilityStatus::Occupied])->count() / $sellableCount * 100, 2)
            : 0.0;

        $scheduledAt = $departure->scheduledDepartureAt();
        $hoursBeforeDeparture = round(Carbon::now()->diffInHours($scheduledAt, true) * ($scheduledAt->isFuture() ? 1 : -1), 2);

        return [$fillRate, $hoursBeforeDeparture];
    }

    private function coefficientFor(?PricingRule $rule): float
    {
        if (! $rule) {
            return 1.0;
        }

        $percentage = (float) $rule->percentage;

        return $rule->type === PricingRuleType::Increase
            ? round(1.0 + ($percentage / 100), 4)
            : round(max(0.0, 1.0 - ($percentage / 100)), 4);
    }

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
