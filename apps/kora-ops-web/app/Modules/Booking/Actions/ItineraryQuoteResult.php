<?php

namespace App\Modules\Booking\Actions;

use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Carbon\CarbonImmutable;

/**
 * Aggregated quote result for a multi-leg itinerary.
 */
final class ItineraryQuoteResult
{
    /**
     * @param  list<PriceQuote>  $legQuotes
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly array $legQuotes,
        public readonly array $snapshot,
        public readonly ItineraryQuote $itineraryQuote,
        public readonly ?CarbonImmutable $expiresAt = null,
    ) {}
}
