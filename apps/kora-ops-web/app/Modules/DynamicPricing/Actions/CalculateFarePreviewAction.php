<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CalculateFarePreviewAction
{
    public function __construct(private CalculateDynamicPriceAction $calculateDynamicPrice) {}

    /**
     * Resolve a fare preview for a departure, applying dynamic pricing.
     *
     * @return array<string, mixed>|null
     */
    public function handleForDeparture(Departure $departure, Stop $originStop, Stop $destinationStop, ?ServiceClass $serviceClass = null): ?array
    {
        $price = $this->calculateDynamicPrice->handle($departure, $originStop, $destinationStop, $serviceClass);
        if (! $price) {
            return null;
        }

        return [
            'fare_uuid' => $price->fare->uuid,
            'base_amount' => $price->baseAmount,
            'final_amount' => $price->finalAmount,
            'coefficient' => $price->coefficient,
            'dynamic_adjustment_amount' => $price->adjustmentAmount(),
            'pricing_rule_uuid' => $price->rule?->uuid,
            'fill_rate' => $price->fillRate,
            'hours_before_departure' => $price->hoursBeforeDeparture,
            'currency' => $price->fare->currency,
            'service_class_code' => $price->fare->serviceClass?->code,
            'service_class_name' => $price->fare->serviceClass?->name,
            'service_class_description' => $price->fare->serviceClass?->description,
            'is_quote' => false,
        ];
    }

    /**
     * Resolve a fare preview for a line and travel date (direction-aware),
     * without dynamic pricing. Used when no departure context is available.
     *
     * @return array<string, mixed>|null
     */
    public function handle(Line $line, Stop $originStop, Stop $destinationStop, CarbonInterface $travelDate, ?string $direction = null, ?ServiceClass $serviceClass = null): ?array
    {
        $fare = $this->resolveFare($line, $originStop, $destinationStop, $travelDate, $direction, $serviceClass);

        if (! $fare) {
            return null;
        }

        return $this->previewFromArray($fare);
    }

    /**
     * Resolve a fare preview from a pre-loaded fares collection, avoiding
     * any database queries. The collection must already contain only active
     * fares with the serviceClass relation eager-loaded.
     *
     * @param  EloquentCollection<int, Fare>  $fares
     * @return array<string, mixed>|null
     */
    public function handleFromLoadedFares(
        EloquentCollection $fares,
        Stop $originStop,
        Stop $destinationStop,
        CarbonInterface $travelDate,
        ?string $direction = null,
        ?ServiceClass $serviceClass = null,
    ): ?array {
        $fare = $this->resolveFareFromCollection($fares, $originStop, $destinationStop, $travelDate, $direction, $serviceClass);

        if (! $fare) {
            return null;
        }

        return $this->previewFromArray($fare);
    }

    /**
     * @return array<string, mixed>
     */
    private function previewFromArray(Fare $fare): array
    {
        return [
            'fare_uuid' => $fare->uuid,
            'base_amount' => $fare->base_amount,
            'final_amount' => $fare->base_amount,
            'currency' => $fare->currency,
            'service_class_code' => $fare->serviceClass?->code,
            'service_class_name' => $fare->serviceClass?->name,
            'service_class_description' => $fare->serviceClass?->description,
            'is_quote' => false,
        ];
    }

    /**
     * Resolve a fare for the requested direction. If no fare matches the
     * exact direction, fall back to the reverse segment (origin/destination
     * swapped) with a 'both' or matching direction.
     */
    private function resolveFare(Line $line, Stop $originStop, Stop $destinationStop, CarbonInterface $travelDate, ?string $direction, ?ServiceClass $serviceClass): ?Fare
    {
        // 1a. Direct search: origin → destination with the exact direction.
        if ($direction !== null) {
            $fare = $this->queryFare($line, $originStop, $destinationStop, $travelDate, [$direction], $serviceClass);
            if ($fare) {
                return $fare;
            }
        }

        // 1b. Direct search: origin → destination with 'both' direction.
        $fare = $this->queryFare($line, $originStop, $destinationStop, $travelDate, [FareDirection::Both->value], $serviceClass);
        if ($fare) {
            return $fare;
        }

        // 2. Fallback: reverse search (destination → origin) with the
        //    opposite direction or 'both'.
        $reverseDirection = match ($direction) {
            FareDirection::Outbound->value => FareDirection::Return->value,
            FareDirection::Return->value => FareDirection::Outbound->value,
            default => null,
        };

        if ($reverseDirection !== null) {
            $fare = $this->queryFare($line, $destinationStop, $originStop, $travelDate, [$reverseDirection], $serviceClass);
            if ($fare) {
                return $fare;
            }
        }

        $fare = $this->queryFare($line, $destinationStop, $originStop, $travelDate, [FareDirection::Both->value], $serviceClass);
        if ($fare) {
            return $fare;
        }

        // 3. Last resort: if no direction was specified, try all directions
        //    on the reverse segment.
        if ($direction === null) {
            return $this->queryFare(
                $line,
                $destinationStop,
                $originStop,
                $travelDate,
                [FareDirection::Outbound->value, FareDirection::Return->value],
                $serviceClass,
            );
        }

        return null;
    }

    /**
     * Same resolution logic as resolveFare, but filters a pre-loaded
     * Eloquent collection instead of issuing database queries.
     *
     * @param  EloquentCollection<int, Fare>  $fares
     */
    private function resolveFareFromCollection(
        EloquentCollection $fares,
        Stop $originStop,
        Stop $destinationStop,
        CarbonInterface $travelDate,
        ?string $direction,
        ?ServiceClass $serviceClass,
    ): ?Fare {
        // 1a. Direct search: origin → destination with the exact direction.
        if ($direction !== null) {
            $fare = $this->filterFares($fares, $originStop, $destinationStop, $travelDate, [$direction], $serviceClass);
            if ($fare) {
                return $fare;
            }
        }

        // 1b. Direct search: origin → destination with 'both' direction.
        $fare = $this->filterFares($fares, $originStop, $destinationStop, $travelDate, [FareDirection::Both->value], $serviceClass);
        if ($fare) {
            return $fare;
        }

        // 2. Fallback: reverse search (destination → origin) with the
        //    opposite direction or 'both'.
        $reverseDirection = match ($direction) {
            FareDirection::Outbound->value => FareDirection::Return->value,
            FareDirection::Return->value => FareDirection::Outbound->value,
            default => null,
        };

        if ($reverseDirection !== null) {
            $fare = $this->filterFares($fares, $destinationStop, $originStop, $travelDate, [$reverseDirection], $serviceClass);
            if ($fare) {
                return $fare;
            }
        }

        $fare = $this->filterFares($fares, $destinationStop, $originStop, $travelDate, [FareDirection::Both->value], $serviceClass);
        if ($fare) {
            return $fare;
        }

        // 3. Last resort: if no direction was specified, try all directions
        //    on the reverse segment.
        if ($direction === null) {
            return $this->filterFares(
                $fares,
                $destinationStop,
                $originStop,
                $travelDate,
                [FareDirection::Outbound->value, FareDirection::Return->value],
                $serviceClass,
            );
        }

        return null;
    }

    /**
     * Filter a pre-loaded fares collection by origin, destination, direction,
     * service class, and effective-date window. Returns the most recently
     * effective fare or null.
     *
     * @param  EloquentCollection<int, Fare>  $fares
     * @param  list<string>  $directions
     */
    private function filterFares(
        EloquentCollection $fares,
        Stop $originStop,
        Stop $destinationStop,
        CarbonInterface $travelDate,
        array $directions,
        ?ServiceClass $serviceClass,
    ): ?Fare {
        $filtered = $fares
            ->where('origin_stop_id', $originStop->id)
            ->where('destination_stop_id', $destinationStop->id)
            ->whereIn('direction', $directions)
            ->filter(fn (Fare $fare): bool => $this->fareEffectiveOn($fare, $travelDate));

        if ($serviceClass !== null) {
            $filtered = $filtered->where('service_class_id', $serviceClass->id);
        }

        return $filtered->sortByDesc('effective_from')->first();
    }

    /**
     * Check whether a fare is effective on the given travel date.
     */
    private function fareEffectiveOn(Fare $fare, CarbonInterface $travelDate): bool
    {
        $from = $fare->effective_from;
        $until = $fare->effective_until;

        if ($from !== null && $from->toDateString() > $travelDate->toDateString()) {
            return false;
        }

        if ($until !== null && $until->toDateString() < $travelDate->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $directions
     */
    private function queryFare(Line $line, Stop $originStop, Stop $destinationStop, CarbonInterface $travelDate, array $directions, ?ServiceClass $serviceClass): ?Fare
    {
        $query = Fare::query()
            ->with('serviceClass')
            ->whereBelongsTo($line)
            ->whereBelongsTo($originStop, 'originStop')
            ->whereBelongsTo($destinationStop, 'destinationStop')
            ->where('status', FareStatus::Active->value)
            ->whereIn('direction', $directions)
            ->whereDate('effective_from', '<=', $travelDate)
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $travelDate));

        if ($serviceClass) {
            $query->where('service_class_id', $serviceClass->id);
        }

        return $query->orderByDesc('effective_from')->first();
    }
}
