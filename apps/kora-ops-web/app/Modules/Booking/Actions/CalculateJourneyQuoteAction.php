<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\JourneyQuote;
use App\Modules\Booking\Models\JourneyQuoteLeg;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JourneyLegQuoteRequest
{
    public function __construct(
        public readonly string $direction,
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly int $legOrder,
    ) {}
}

final class JourneyQuoteResult
{
    public function __construct(public readonly JourneyQuote $journeyQuote) {}
}

class CalculateJourneyQuoteAction
{
    public function __construct(
        private readonly CalculateQuoteAction $calculateQuote,
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly GroupPricingService $groupPricing,
    ) {}

    /**
     * @param  list<JourneyLegQuoteRequest>  $outbound
     * @param  list<JourneyLegQuoteRequest>  $inbound
     *
     * @throws ValidationException
     */
    public function handle(
        string $tripType,
        array $outbound,
        array $inbound,
        int $passengerCount,
        ?User $user = null,
    ): JourneyQuoteResult {
        if (! in_array($tripType, ['one_way', 'round_trip'], true)) {
            throw ValidationException::withMessages(['trip_type' => 'Le type de parcours est invalide.']);
        }

        if ($outbound === [] || ($tripType === 'round_trip' && $inbound === [])) {
            throw ValidationException::withMessages(['legs' => 'Les deux directions du parcours sont obligatoires.']);
        }

        if ($passengerCount < 1 || $passengerCount > GroupBookingSetting::current()->maximum_passengers) {
            throw ValidationException::withMessages(['passenger_count' => 'Le nombre de passagers est invalide.']);
        }

        $this->validateDirection($outbound);
        if ($inbound !== []) {
            $this->validateDirection($inbound);
        }

        return DB::transaction(function () use ($tripType, $outbound, $inbound, $passengerCount, $user): JourneyQuoteResult {
            $requests = [...$outbound, ...$inbound];
            $quotes = [];
            $unitAmounts = [];
            $adjustments = [];
            $snapshotLegs = [];
            $currency = null;
            $expiresAt = null;

            foreach ($requests as $leg) {
                $quote = $this->calculateQuote->handle(
                    departure: $leg->departure,
                    originStop: $leg->originStop,
                    destinationStop: $leg->destinationStop,
                    passengerCount: 1,
                );
                $quotes[] = [$leg, $quote];
                $unitAmounts[] = (int) $quote->final_amount;
                $adjustments[] = (int) $quote->final_amount - (int) $quote->base_amount;

                if ($currency !== null && $currency !== $quote->currency) {
                    throw ValidationException::withMessages(['legs' => 'Toutes les jambes doivent utiliser la même devise.']);
                }
                $currency ??= $quote->currency;
                if ($quote->expires_at !== null && ($expiresAt === null || $quote->expires_at < $expiresAt)) {
                    $expiresAt = CarbonImmutable::instance($quote->expires_at);
                }

                $snapshotLegs[] = [
                    'direction' => $leg->direction,
                    'leg_order' => $leg->legOrder,
                    'quote_uuid' => $quote->uuid,
                    'departure_uuid' => $leg->departure->uuid,
                    'origin_stop_uuid' => $leg->originStop->uuid,
                    'destination_stop_uuid' => $leg->destinationStop->uuid,
                    'departure_at' => $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop)?->toIso8601String(),
                    'arrival_at' => $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop)?->toIso8601String(),
                ];
            }

            $pricing = $this->groupPricing->calculate(
                unitAmounts: $unitAmounts,
                passengerCount: $passengerCount,
                currency: $currency,
                dynamicAdjustments: $adjustments,
                expiresAt: $expiresAt,
            );
            $snapshot = [
                'type' => 'journey',
                'trip_type' => $tripType,
                'passenger_count' => $passengerCount,
                'legs' => $snapshotLegs,
                'subtotal_amount' => $pricing['subtotal_amount'],
                'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
                'group_discount' => $pricing['group_discount'],
                'passenger_allocations' => $pricing['passenger_allocations'],
                'total_amount' => $pricing['total_amount'],
            ];
            $journeyQuote = JourneyQuote::query()->create([
                'user_id' => $user?->getKey(),
                'trip_type' => $tripType,
                'passenger_count' => $passengerCount,
                'subtotal_amount' => $pricing['subtotal_amount'],
                'dynamic_adjustment_amount' => $pricing['dynamic_adjustment_amount'],
                'group_discount_amount' => $pricing['group_discount']['amount'],
                'total_amount' => $pricing['total_amount'],
                'currency' => $pricing['currency'],
                'snapshot' => $snapshot,
                'expires_at' => $expiresAt ?? now()->addMinutes(10),
            ]);

            foreach ($quotes as [$leg, $quote]) {
                JourneyQuoteLeg::query()->create([
                    'journey_quote_id' => $journeyQuote->getKey(),
                    'direction' => $leg->direction,
                    'leg_order' => $leg->legOrder,
                    'price_quote_id' => $quote->getKey(),
                    'departure_id' => $leg->departure->getKey(),
                    'origin_stop_id' => $leg->originStop->getKey(),
                    'destination_stop_id' => $leg->destinationStop->getKey(),
                    'departure_at' => $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop),
                    'arrival_at' => $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop),
                    'amount' => $quote->final_amount,
                ]);
            }

            return new JourneyQuoteResult($journeyQuote->load('legs'));
        });
    }

    /** @param list<JourneyLegQuoteRequest> $legs */
    private function validateDirection(array $legs): void
    {
        if (count($legs) > (int) config('kora.itineraries.max_legs', 3)) {
            throw ValidationException::withMessages(['legs' => 'Le nombre maximal de tronçons est dépassé.']);
        }

        $usedDepartures = [];
        foreach ($legs as $index => $leg) {
            if (! in_array($leg->direction, ['outbound', 'inbound'], true) || $leg->legOrder !== $index + 1) {
                throw ValidationException::withMessages(["legs.{$index}" => 'La direction ou l’ordre du tronçon est invalide.']);
            }
            if (in_array($leg->departure->getKey(), $usedDepartures, true)) {
                throw ValidationException::withMessages(["legs.{$index}.departure_uuid" => 'Un même départ ne peut pas être utilisé deux fois.']);
            }
            $usedDepartures[] = $leg->departure->getKey();
            if ($leg->originStop->line_id !== $leg->departure->line_id || $leg->destinationStop->line_id !== $leg->departure->line_id) {
                throw ValidationException::withMessages(["legs.{$index}.origin_stop_uuid" => 'Les arrêts ne correspondent pas à la ligne du départ.']);
            }
            if ($leg->originStop->stop_order >= $leg->destinationStop->stop_order) {
                throw ValidationException::withMessages(["legs.{$index}.destination_stop_uuid" => 'La destination doit être après l’origine.']);
            }
            $departureAt = $this->stopTimeResolver->departureAt($leg->departure, $leg->originStop);
            $arrivalAt = $this->stopTimeResolver->arrivalAt($leg->departure, $leg->destinationStop);
            if ($departureAt === null || $arrivalAt === null || $arrivalAt->lte($departureAt)) {
                throw ValidationException::withMessages(["legs.{$index}" => 'Les horaires de ce tronçon sont incomplets.']);
            }
            if ($index > 0) {
                $previous = $legs[$index - 1];
                if ($previous->destinationStop->physical_stop_id !== $leg->originStop->physical_stop_id) {
                    throw ValidationException::withMessages(["legs.{$index}.origin_stop_uuid" => 'La correspondance ne se fait pas au même arrêt physique.']);
                }
                $previousArrivalAt = $this->stopTimeResolver->arrivalAt($previous->departure, $previous->destinationStop);
                $margin = (int) config('kora.itineraries.connection_margin_minutes', 45);
                if ($previousArrivalAt === null || $departureAt->lt($previousArrivalAt->addMinutes($margin))) {
                    throw ValidationException::withMessages(["legs.{$index}.departure_uuid" => 'La marge minimale de correspondance n’est pas respectée.']);
                }
            }
        }
    }
}
