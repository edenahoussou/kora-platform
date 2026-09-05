<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Data\PricingBreakdownData;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        public readonly ItineraryQuote $itineraryQuote,
        public readonly ?CarbonImmutable $expiresAt = null,
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
        private DepartureStopTimeResolver $stopTimeResolver,
        private GroupPricingService $groupPricing,
    ) {}

    /**
     * @param  list<ItineraryLegQuoteRequest>  $legs
     *
     * @throws ValidationException
     */
    public function handle(
        array $legs,
        int $passengerCount = 1,
        ?User $user = null,
    ): ItineraryQuoteResult {
        if ($legs === []) {
            throw ValidationException::withMessages([
                'legs' => 'Au moins une jambe est requise pour le devis.',
            ]);
        }

        if ($passengerCount < 1 || $passengerCount > GroupBookingSetting::current()->maximum_passengers) {
            throw ValidationException::withMessages([
                'passenger_count' => 'Le nombre de passagers doit être compris entre 1 et 10.',
            ]);
        }

        if (count($legs) > (int) config('kora.itineraries.max_legs', 3)) {
            throw ValidationException::withMessages([
                'legs' => 'Le nombre maximal de jambes autorisé est dépassé.',
            ]);
        }

        $this->validateLegs($legs);

        return DB::transaction(function () use ($legs, $passengerCount, $user): ItineraryQuoteResult {
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
                    passengerCount: 1,
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
                    'departure_at' => $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop)?->toIso8601String(),
                    'arrival_at' => $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop)?->toIso8601String(),
                    'origin_physical_stop_uuid' => $leg->originStop->physicalStop?->uuid,
                    'destination_physical_stop_uuid' => $leg->destinationStop->physicalStop?->uuid,
                ];

                $totalAmount += (int) $quote->final_amount * $passengerCount;

                if ($index === 0) {
                    $currency = $quote->currency;
                } elseif ($quote->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'legs' => 'Toutes les jambes doivent utiliser la même devise.',
                    ]);
                }

                if ($quote->expires_at && ($earliestExpiry === null || $quote->expires_at < $earliestExpiry)) {
                    $earliestExpiry = CarbonImmutable::instance($quote->expires_at);
                }
            }

            $pricing = $this->groupPricing->calculate(
                unitAmounts: array_map(fn (PriceQuote $quote): int => (int) $quote->final_amount, $legQuotes),
                passengerCount: $passengerCount,
                currency: $currency,
                dynamicAdjustments: array_map(fn (PriceQuote $quote): int => (int) $quote->final_amount - (int) $quote->base_amount, $legQuotes),
                expiresAt: $earliestExpiry,
            );

            $totalAmount = $pricing['total_amount'];
            $breakdown = PricingBreakdownData::fromGroupPricing($pricing);
            $snapshot = [
                'type' => 'itinerary',
                'legs_count' => count($legs),
                'passenger_count' => $passengerCount,
                'total_amount' => $totalAmount,
                'subtotal_amount' => $pricing['subtotal_amount'],
                'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
                'group' => $pricing['group'],
                'group_discount' => $pricing['group_discount'],
                'passenger_allocations' => $pricing['passenger_allocations'],
                'legs' => $legSnapshots,
                'pricing_breakdown' => $breakdown->toArray(),
            ];

            $itineraryQuote = ItineraryQuote::create([
                'user_id' => $user?->id,
                'passenger_count' => $passengerCount,
                'subtotal_amount' => $pricing['subtotal_amount'],
                'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
                'group_discount_amount' => $pricing['group_discount']['amount'],
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'snapshot' => $snapshot,
                'expires_at' => $earliestExpiry ?? now()->addMinutes(10),
            ]);

            foreach ($legs as $index => $leg) {
                ItineraryQuoteLeg::create([
                    'itinerary_quote_id' => $itineraryQuote->id,
                    'leg_order' => $index + 1,
                    'price_quote_id' => $legQuotes[$index]->id,
                    'departure_id' => $leg->departure->id,
                    'origin_stop_id' => $leg->originStop->id,
                    'destination_stop_id' => $leg->destinationStop->id,
                    'amount' => (int) $legQuotes[$index]->final_amount,
                ]);
            }

            return new ItineraryQuoteResult(
                totalAmount: $totalAmount,
                currency: $currency,
                legQuotes: $legQuotes,
                snapshot: $snapshot,
                itineraryQuote: $itineraryQuote->load('legs'),
                expiresAt: $earliestExpiry,
            );
        });
    }

    /**
     * @param  list<ItineraryLegQuoteRequest>  $legs
     *
     * @throws ValidationException
     */
    private function validateLegs(array $legs): void
    {
        $departureIds = [];

        foreach ($legs as $index => $leg) {
            if (in_array($leg->departure->id, $departureIds, true)) {
                throw ValidationException::withMessages([
                    "legs.{$index}.departure_uuid" => 'Un même départ ne peut pas être utilisé plusieurs fois.',
                ]);
            }

            $departureIds[] = $leg->departure->id;

            if ($leg->originStop->line_id !== $leg->departure->line_id || $leg->destinationStop->line_id !== $leg->departure->line_id) {
                throw ValidationException::withMessages([
                    "legs.{$index}.origin_stop_uuid" => 'Les arrêts spécifiés n\'appartiennent pas à la ligne du départ.',
                ]);
            }

            if ($leg->originStop->stop_order >= $leg->destinationStop->stop_order) {
                throw ValidationException::withMessages([
                    "legs.{$index}.destination_stop_uuid" => 'L\'arrêt de destination doit être situé après l\'arrêt d\'origine.',
                ]);
            }

            if ($index > 0 && $legs[$index - 1]->destinationStop->physical_stop_id !== $leg->originStop->physical_stop_id) {
                throw ValidationException::withMessages([
                    "legs.{$index}.origin_stop_uuid" => 'La correspondance ne se fait pas au même point d\'arrêt physique.',
                ]);
            }

            $departureAt = $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop);
            $arrivalAt = $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop);
            if ($departureAt === null || $arrivalAt === null || $arrivalAt->lte($departureAt)) {
                throw ValidationException::withMessages([
                    "legs.{$index}" => 'Les horaires publiés de cette jambe sont incomplets ou incohérents.',
                ]);
            }

            if ($index > 0) {
                $previousArrivalAt = $this->stopTimeResolver->arrivalAt(
                    $legs[$index - 1]->departure,
                    $legs[$index - 1]->destinationStop,
                );
                $margin = (int) config('kora.itineraries.connection_margin_minutes', 45);

                if ($previousArrivalAt === null || $departureAt->lt($previousArrivalAt->addMinutes($margin))) {
                    throw ValidationException::withMessages([
                        "legs.{$index}.departure_uuid" => 'La marge minimale de correspondance n’est pas respectée.',
                    ]);
                }
            }

            $serviceDate = $legs[0]->departure->departure_date?->toDateString();
            if ($serviceDate !== null && $departureAt->setTimezone(config('app.timezone'))->toDateString() !== $serviceDate) {
                throw ValidationException::withMessages([
                    "legs.{$index}.departure_uuid" => 'Toutes les jambes doivent appartenir à la même journée de service.',
                ]);
            }
        }
    }
}
