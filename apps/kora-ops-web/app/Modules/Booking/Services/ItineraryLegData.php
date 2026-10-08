<?php

namespace App\Modules\Booking\Services;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;

/**
 * Itinerary data for a single leg of a multi-line journey.
 */
final class ItineraryLegData
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly int $legOrder,
        public readonly int $amount,
        public readonly CarbonImmutable $departureAt,
        public readonly CarbonImmutable $arrivalAt,
        public readonly int $availableSeatsCount = 0,
        public readonly ?string $serviceClassCode = null,
        public readonly ?string $serviceClassName = null,
        public readonly ?int $estimatedDurationMinutes = null,
        public readonly ?Bus $bus = null,
        public readonly ?int $baseAmount = null,
        public readonly ?float $coefficient = null,
        public readonly ?int $finalAmount = null,
        public readonly ?int $dynamicAdjustmentAmount = null,
        public readonly ?string $pricingRuleUuid = null,
        public readonly ?float $fillRate = null,
        public readonly ?float $hoursBeforeDeparture = null,
    ) {}
}
