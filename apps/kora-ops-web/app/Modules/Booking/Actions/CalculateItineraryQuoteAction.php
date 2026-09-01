<?php

namespace App\Modules\Booking\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Leg data for a multi-leg quote request.
 */
final class ItineraryLegQuoteRequest
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
    ) {}
}

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
        public readonly ?Carbon $expiresAt = null,
    ) {}
}

/**
 * Calculates an aggregated quote for a multi-leg itinerary.
 *
 * Reuses the existing per-leg pricing engine (CalculateQuoteAction) for each
 * leg, then sums the final amounts. No new pricing engine is introduced —
 * the multi-line quote is an aggregation of single-line quotes.
 */
class CalculateItineraryQuoteAction
{
    public function __construct(
        private CalculateQuoteAction $calculateQuote,
    ) {}

    /**
     * @param  list<ItineraryLegQuoteRequest>  $legs
     *
     * @throws ValidationException
     */
    public function handle(array $legs, int $passengerCount = 1): ItineraryQuoteResult
    {
        if ($legs === []) {
            throw ValidationException::withMessages([
                'legs' => 'Au moins une jambe est requise pour le devis.',
            ]);
        }

        $legQuotes = [];
        $legSnapshots = [];
        $totalAmount = 0;
        $currency = 'XOF';
        $earliestExpiry = null;

        foreach ($legs as $index => $leg) {
            $quote = $this->calculateQuote->handle(
                departure: $leg->departure,
                originStop: $leg->originStop,
                destinationStop: $leg->destinationStop,
                passengerCount: $passengerCount,
            );

            $legQuotes[] = $quote;
            $legSnapshots[] = [
                'leg_order' => $index + 1,
                'quote_uuid' => $quote->uuid,
                'departure_uuid' => $leg->departure->uuid,
                'origin_stop_uuid' => $leg->originStop->uuid,
                'destination_stop_uuid' => $leg->destinationStop->uuid,
                'base_amount' => $quote->base_amount,
                'coefficient' => $quote->coefficient,
                'final_amount' => $quote->final_amount,
                'passenger_count' => $passengerCount,
                'line_code' => $leg->departure->line->code,
            ];

            $totalAmount += (int) $quote->final_amount * $passengerCount;

            // Toutes les jambes d'un même itinéraire doivent partager la même devise.
            // Un itinéraire transfrontalier XOF/XAF doit être explicitement refusé
            // plutôt que de produire un total incohérent avec une devise arbitraire.
            if ($index === 0) {
                $currency = $quote->currency;
            } elseif ($quote->currency !== $currency) {
                throw ValidationException::withMessages([
                    'legs' => 'Toutes les jambes doivent utiliser la même devise.',
                ]);
            }

            if ($quote->expires_at && ($earliestExpiry === null || $quote->expires_at < $earliestExpiry)) {
                $earliestExpiry = $quote->expires_at;
            }
        }

        $snapshot = [
            'type' => 'itinerary',
            'legs_count' => count($legs),
            'passenger_count' => $passengerCount,
            'total_amount' => $totalAmount,
            'legs' => $legSnapshots,
        ];

        return new ItineraryQuoteResult(
            totalAmount: $totalAmount,
            currency: $currency,
            legQuotes: $legQuotes,
            snapshot: $snapshot,
            expiresAt: $earliestExpiry,
        );
    }
}
