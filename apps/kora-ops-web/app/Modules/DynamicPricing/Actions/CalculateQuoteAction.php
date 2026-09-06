<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Validation\ValidationException;

class CalculateQuoteAction
{
    public function __construct(
        private ResolveDepartureSellabilityAction $resolveSellability,
        private CalculateDynamicPriceAction $calculateDynamicPrice,
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

        $price = $this->calculateDynamicPrice->handle($departure, $originStop, $destinationStop, $serviceClass);
        if (! $price) {
            throw ValidationException::withMessages([
                'origin_stop_uuid' => 'Aucun tarif actif n\'a été trouvé pour ce trajet.',
            ]);
        }

        $pricing = $this->groupPricing->calculate(
            unitAmounts: [$price->finalAmount],
            passengerCount: $passengerCount,
            currency: $price->fare->currency,
            dynamicAdjustments: [$price->adjustmentAmount()],
        );

        $snapshot = [
            'fare_uuid' => $price->fare->uuid,
            'line_code' => $departure->line->code,
            'origin_stop' => $originStop->name,
            'destination_stop' => $destinationStop->name,
            'service_class' => $price->fare->serviceClass->name ?? 'Standard',
            'passenger_count' => $passengerCount,
            'subtotal_amount' => $pricing['subtotal_amount'],
            'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
            'group' => $pricing['group'],
            'group_discount' => $pricing['group_discount'],
            'passenger_allocations' => $pricing['passenger_allocations'],
            'total_amount' => $pricing['total_amount'],
            'pricing_rule_uuid' => $price->rule?->uuid,
            'fill_rate_at_quote' => $price->fillRate,
            'hours_before_departure_at_quote' => $price->hoursBeforeDeparture,
        ];

        return PriceQuote::create([
            'departure_id' => $departure->id,
            'origin_stop_id' => $originStop->id,
            'destination_stop_id' => $destinationStop->id,
            'fare_id' => $price->fare->id,
            'pricing_rule_id' => $price->rule?->id,
            'base_amount' => $price->baseAmount,
            'coefficient' => $price->coefficient,
            'final_amount' => $price->finalAmount,
            'passenger_count' => $passengerCount,
            'subtotal_amount' => $pricing['subtotal_amount'],
            'group_discount_amount' => $pricing['group_discount']['amount'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $price->fare->currency,
            'snapshot' => $snapshot,
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
