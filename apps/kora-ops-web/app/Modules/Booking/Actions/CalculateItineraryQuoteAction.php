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
 *
 * `direction` distinguishes the outbound trip from the return trip of an
 * aller-retour: a quote can carry legs from both directions at once, all
 * priced and held together as a single reservation.
 */
final class ItineraryLegQuoteRequest
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly string $direction = 'outbound',
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
                    'direction' => $leg->direction,
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
            $tripType = collect($legs)->contains(fn (ItineraryLegQuoteRequest $leg): bool => $leg->direction === 'inbound')
                ? 'round_trip'
                : 'one_way';
            $snapshot = [
                'type' => 'itinerary',
                'trip_type' => $tripType,
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
                    'direction' => $leg->direction,
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
     * Validates continuity within each direction (outbound legs must chain
     * into one another the same way inbound legs do) and, when both
     * directions are present, that the return trip actually goes back to
     * where the outbound trip started.
     *
     * Connections, same-service-day and margin rules only apply between
     * consecutive legs of the *same* direction — the return can depart
     * days after the outbound arrives.
     *
     * @param  list<ItineraryLegQuoteRequest>  $legs
     *
     * @throws ValidationException
     */
    private function validateLegs(array $legs): void
    {
        $departureIds = [];
        $indexesByDirection = [];

        foreach ($legs as $index => $leg) {
            $indexesByDirection[$leg->direction][] = $index;
        }

        foreach ($indexesByDirection as $direction => $indexes) {
            if (count($indexes) > (int) config('kora.itineraries.max_legs', 3)) {
                throw ValidationException::withMessages([
                    'legs' => "Le nombre maximal de jambes autorisé est dépassé pour la direction {$direction}.",
                ]);
            }
        }

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

            // Validate segment order using the departure's stop_times, not the
            // line's stop_order. Return departures have reversed stop_times.
            if (! $this->isValidSegmentOrder($leg->departure, $leg->originStop, $leg->destinationStop)) {
                throw ValidationException::withMessages([
                    "legs.{$index}.destination_stop_uuid" => 'L\'arrêt de destination doit être situé après l\'arrêt d\'origine dans le sens de circulation de ce départ.',
                ]);
            }

            $departureAt = $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop);
            $arrivalAt = $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop);
            if ($departureAt === null || $arrivalAt === null || $arrivalAt->lte($departureAt)) {
                throw ValidationException::withMessages([
                    "legs.{$index}" => 'Les horaires publiés de cette jambe sont incomplets ou incohérents.',
                ]);
            }

            $positionInDirection = array_search($index, $indexesByDirection[$leg->direction], true);

            if ($positionInDirection > 0) {
                $previousLeg = $legs[$indexesByDirection[$leg->direction][$positionInDirection - 1]];

                if ($previousLeg->destinationStop->physical_stop_id !== $leg->originStop->physical_stop_id) {
                    throw ValidationException::withMessages([
                        "legs.{$index}.origin_stop_uuid" => 'La correspondance ne se fait pas au même point d\'arrêt physique.',
                    ]);
                }

                $previousArrivalAt = $this->stopTimeResolver->arrivalAt(
                    $previousLeg->departure,
                    $previousLeg->destinationStop,
                );
                $margin = (int) config('kora.itineraries.connection_margin_minutes', 45);

                if ($previousArrivalAt === null || $departureAt->lt($previousArrivalAt->addMinutes($margin))) {
                    throw ValidationException::withMessages([
                        "legs.{$index}.departure_uuid" => 'La marge minimale de correspondance n’est pas respectée.',
                    ]);
                }

                // Les jambes d'une même direction peuvent s'enchaîner sur des
                // journées de service différentes (correspondances de nuit,
                // escales inter-journées). La marge minimale de correspondance
                // et l'ordre chronologique des départs suffisent à garantir un
                // itinéraire physiquement enchaînable.
            }
        }

        $this->validateRoundTripConsistency($legs, $indexesByDirection);
    }

    /**
     * @param  list<ItineraryLegQuoteRequest>  $legs
     * @param  array<string, list<int>>  $indexesByDirection
     *
     * @throws ValidationException
     */
    private function validateRoundTripConsistency(array $legs, array $indexesByDirection): void
    {
        $outboundIndexes = $indexesByDirection['outbound'] ?? [];
        $inboundIndexes = $indexesByDirection['inbound'] ?? [];

        if ($outboundIndexes === [] || $inboundIndexes === []) {
            return;
        }

        $outboundFirst = $legs[$outboundIndexes[0]];
        $outboundLast = $legs[$outboundIndexes[count($outboundIndexes) - 1]];
        $inboundFirst = $legs[$inboundIndexes[0]];
        $inboundLast = $legs[$inboundIndexes[count($inboundIndexes) - 1]];

        if ($inboundFirst->originStop->physical_stop_id !== $outboundLast->destinationStop->physical_stop_id) {
            throw ValidationException::withMessages([
                'inbound_legs' => 'Le retour doit partir du point d’arrivée de l’aller.',
            ]);
        }

        if ($inboundLast->destinationStop->physical_stop_id !== $outboundFirst->originStop->physical_stop_id) {
            throw ValidationException::withMessages([
                'inbound_legs' => 'Le retour doit ramener le passager à son point de départ.',
            ]);
        }

        // The return trip cannot start before the outbound trip ends. For a
        // multi-leg outbound, the outbound only ends at the arrival of its
        // last leg, so we compare the inbound's first departure against the
        // outbound's last arrival (not the outbound's first departure).
        $outboundLastArrivalAt = $this->stopTimeResolver->arrivalAt($outboundLast->departure, $outboundLast->destinationStop);
        $inboundFirstDepartureAt = $this->stopTimeResolver->departureAt($inboundFirst->departure, $inboundFirst->originStop);

        if ($outboundLastArrivalAt !== null && $inboundFirstDepartureAt !== null) {
            $roundTripMargin = (int) config('kora.itineraries.round_trip_margin_minutes', 0);
            if ($inboundFirstDepartureAt->lt($outboundLastArrivalAt->addMinutes($roundTripMargin))) {
                throw ValidationException::withMessages([
                    'inbound_legs' => 'Le retour doit partir après l’arrivée de l’aller.',
                ]);
            }
        }

        // Fallback when stop times are unavailable: compare first departures.
        $outboundFirstDepartureAt = $this->stopTimeResolver->departureAt($outboundFirst->departure, $outboundFirst->originStop);

        if ($outboundLastArrivalAt === null || $inboundFirstDepartureAt === null) {
            if ($outboundFirstDepartureAt !== null && $inboundFirstDepartureAt !== null && $inboundFirstDepartureAt->lte($outboundFirstDepartureAt)) {
                throw ValidationException::withMessages([
                    'inbound_legs' => 'La date de retour doit être postérieure à la date de départ.',
                ]);
            }
        }
    }

    /**
     * Check if the origin stop appears before the destination stop in the
     * departure's stop_times. Falls back to stop_order if stop_times is empty.
     */
    private function isValidSegmentOrder(Departure $departure, Stop $originStop, Stop $destinationStop): bool
    {
        $stopTimes = $departure->stop_times ?? [];

        if ($stopTimes === []) {
            return $originStop->stop_order < $destinationStop->stop_order;
        }

        $originIndex = null;
        $destinationIndex = null;

        foreach ($stopTimes as $index => $stopTime) {
            if (($stopTime['stop_uuid'] ?? null) === $originStop->uuid) {
                $originIndex = $index;
            }
            if (($stopTime['stop_uuid'] ?? null) === $destinationStop->uuid) {
                $destinationIndex = $index;
            }
        }

        if ($originIndex === null || $destinationIndex === null) {
            return $originStop->stop_order < $destinationStop->stop_order;
        }

        return $originIndex < $destinationIndex;
    }
}
