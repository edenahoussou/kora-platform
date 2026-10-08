<?php

namespace App\Modules\Booking\Services;

use Carbon\CarbonImmutable;

/**
 * Itinerary data for a complete multi-line journey (one or more legs).
 */
final class ItineraryData
{
    /**
     * @param  list<ItineraryLegData>  $legs
     */
    public function __construct(
        public readonly array $legs,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly int $connectionsCount,
        public readonly CarbonImmutable $departureAt,
        public readonly CarbonImmutable $arrivalAt,
        public readonly int $connectionMarginMinutes,
    ) {}

    public function isDirect(): bool
    {
        return $this->connectionsCount === 0;
    }
}
