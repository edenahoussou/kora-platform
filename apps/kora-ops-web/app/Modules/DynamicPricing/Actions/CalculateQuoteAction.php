<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Validation\ValidationException;

class CalculateQuoteAction
{
    public function __construct(private ResolveDepartureSellabilityAction $resolveSellability) {}

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

        $rule = PricingRule::query()
            ->where(fn ($query) => $query->where('line_id', $departure->line_id)->orWhereNull('line_id'))
            ->where('status', PricingRuleStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('priority')
            ->first();

        $coefficient = 1.0;
        if ($rule) {
            $percentage = (float) $rule->percentage;
            if ($rule->type === PricingRuleType::Increase) {
                $coefficient = round(1.0 + ($percentage / 100), 4);
            } elseif ($rule->type === PricingRuleType::Decrease) {
                $coefficient = round(max(0.0, 1.0 - ($percentage / 100)), 4);
            }
        }

        $baseAmount = (int) $fare->base_amount;
        $finalAmount = (int) round($baseAmount * $coefficient);
        $totalAmount = $finalAmount * $passengerCount;

        $snapshot = [
            'fare_uuid' => $fare->uuid,
            'line_code' => $departure->line->code,
            'origin_stop' => $originStop->name,
            'destination_stop' => $destinationStop->name,
            'service_class' => $fare->serviceClass->name ?? 'Standard',
            'passenger_count' => $passengerCount,
            'total_amount' => $totalAmount,
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
            'currency' => $fare->currency,
            'snapshot' => $snapshot,
            'expires_at' => now()->addMinutes((int) config('kora.booking.quote_ttl_minutes', 10)),
        ]);
    }
}
