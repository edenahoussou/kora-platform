<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\ResolvePayerContextAction;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Http\Requests\ItineraryHoldRequest;
use App\Modules\Booking\Http\Requests\ItineraryQuoteRequest;
use App\Modules\Booking\Http\Requests\ItinerarySearchRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Services\ItineraryData;
use App\Modules\Booking\Services\ItineraryLegData;
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * @group Bus
 *
 * Multi-line itinerary endpoints for journeys with connections.
 */
class ItineraryController extends Controller
{
    public function __construct(
        private ItinerarySearchService $itinerarySearch,
    ) {}

    /**
     * Search for multi-line itineraries (direct and with connections).
     *
     * Returns rich leg data (line, bus with amenities, origin/destination stops
     * with city and country, available seats count, service class) so the
     * mobile client can render search results without extra calls.
     *
     * @queryParam from_physical_stop_uuid string required UUID of the origin physical stop.
     * @queryParam to_physical_stop_uuid string required UUID of the destination physical stop.
     * @queryParam date string required Travel date (Y-m-d). Example: 2026-09-07
     * @queryParam passenger_count integer Number of passengers. Defaults to 1.
     * @queryParam sort string Sort key: departure_time, arrival_time, price, duration, connections. Defaults to connections.
     * @queryParam sort_direction string Sort direction: asc or desc. Defaults to asc.
     * @queryParam service_class_code string Filter by service class code (e.g. standard, confort).
     * @queryParam trip_type string Trip type: one_way or round_trip. Defaults to one_way. When round_trip, return_date is required and the response shape changes to { outbound: [...], inbound: [...] }.
     * @queryParam return_date string Return date (Y-m-d). Required when trip_type is round_trip. Example: 2026-09-14
     *
     * @bodyParam date string required Travel date (Y-m-d). Example: 2026-09-07
     * @bodyParam return_date string Return date (Y-m-d). Required when trip_type is round_trip. Example: 2026-09-14
     *
     * @responseField data.legs.base_amount integer Static base fare amount before dynamic pricing adjustment.
     * @responseField data.legs.coefficient number Multiplier applied to the base fare (e.g. 1.20 for +20 %, 0.90 for -10 %). Always non-negative.
     * @responseField data.legs.final_amount integer Dynamically adjusted fare actually charged to the customer (round(base_amount * coefficient)).
     * @responseField data.legs.dynamic_adjustment_amount integer Currency difference between final and base amount (final_amount - base_amount). Can be negative for discounts.
     * @responseField data.legs.pricing_rule_uuid string UUID of the pricing rule that matched, or null when no rule applied.
     * @responseField data.legs.fill_rate number Percentage of sellable seats held or occupied on the leg segment (0-100).
     * @responseField data.legs.hours_before_departure number Hours between now and the scheduled departure. Negative when the departure is in the past.
     *
     * @response 200 scenario="One-way itineraries" {
     *   "data": [
     *     {
     *       "connections_count": 0,
     *       "total_amount": 15600,
     *       "currency": "XOF",
     *       "departure_at": "2026-09-07T13:00:00+00:00",
     *       "arrival_at": "2026-09-07T23:00:00+00:00",
     *       "connection_margin_minutes": 45,
     *       "legs": [
     *         {
     *           "leg_order": 1,
     *           "departure_uuid": "eaaf800b-90b6-4334-b840-7122d9ed65ac",
     *           "line": {
     *             "uuid": "line-uuid",
     *             "code": "L2",
     *             "name": "Cotonou - Malanville",
     *             "estimated_duration_minutes": 600,
     *             "is_international": false
     *           },
     *           "bus": {
     *             "uuid": "bus-uuid",
     *             "registration_number": "BJ-1234-AA",
     *             "brand": "Toyota",
     *             "model": "Coaster",
     *             "seat_count": 30,
     *             "amenities": [
     *               { "code": "wifi", "label": "Wi-Fi", "icon": "wifi" }
     *             ]
     *           },
     *           "origin_stop": {
     *             "uuid": "stop-uuid",
     *             "name": "Cotonou",
     *             "city": "Cotonou",
     *             "country": "BJ",
     *             "stop_order": 1
     *           },
     *           "destination_stop": {
     *             "uuid": "stop-uuid",
     *             "name": "Malanville",
     *             "city": "Malanville",
     *             "country": "BJ",
     *             "stop_order": 7
     *           },
     *           "amount": 15600,
     *           "base_amount": 13000,
     *           "coefficient": 1.2,
     *           "final_amount": 15600,
     *           "dynamic_adjustment_amount": 2600,
     *           "pricing_rule_uuid": null,
     *           "fill_rate": 75,
     *           "hours_before_departure": 10,
     *           "available_seats_count": 28,
     *           "service_class_code": "standard",
     *           "service_class_name": "Standard",
     *           "departure_at": "2026-09-07T13:00:00+00:00",
     *           "arrival_at": "2026-09-07T23:00:00+00:00"
     *         }
     *       ]
     *     }
     *   ]
     * }
     * @response 200 scenario="Round-trip itineraries" {
     *   "outbound": [
     *     {
     *       "connections_count": 0,
     *       "total_amount": 15600,
     *       "currency": "XOF",
     *       "departure_at": "2026-09-07T13:00:00+00:00",
     *       "arrival_at": "2026-09-07T23:00:00+00:00",
     *       "connection_margin_minutes": 45,
     *       "legs": [...]
     *     }
     *   ],
     *   "inbound": [
     *     {
     *       "connections_count": 0,
     *       "total_amount": 15600,
     *       "currency": "XOF",
     *       "departure_at": "2026-09-14T13:00:00+00:00",
     *       "arrival_at": "2026-09-14T23:00:00+00:00",
     *       "connection_margin_minutes": 45,
     *       "legs": [...]
     *     }
     *   ]
     * }
     */
    public function index(ItinerarySearchRequest $request): JsonResponse
    {
        $fromUuid = $request->string('from_physical_stop_uuid')->toString();
        $toUuid = $request->string('to_physical_stop_uuid')->toString();
        $date = CarbonImmutable::parse($request->string('date'));
        $passengerCount = (int) $request->integer('passenger_count', 1);
        $sortBy = $request->sortBy();
        $sortDirection = $request->sortDirection();
        $serviceClassCode = $request->serviceClassCode();

        if ($request->tripType() === 'round_trip') {
            $outbound = $this->itinerarySearch->search(
                originPhysicalStopUuid: $fromUuid,
                destinationPhysicalStopUuid: $toUuid,
                date: $date,
                passengerCount: $passengerCount,
                sortBy: $sortBy,
                sortDirection: $sortDirection,
                serviceClassCode: $serviceClassCode,
            );

            if ($request->returnDate() !== null) {
                $inbound = $this->itinerarySearch->search(
                    originPhysicalStopUuid: $toUuid,
                    destinationPhysicalStopUuid: $fromUuid,
                    date: $request->returnDate(),
                    passengerCount: $passengerCount,
                    sortBy: $sortBy,
                    sortDirection: $sortDirection,
                    serviceClassCode: $serviceClassCode,
                );
            } else {
                // No return date — search upcoming return departures starting
                // from the day after the outbound date, matching the counter-sale
                // behavior so the mobile user sees available return trips.
                $inbound = $this->itinerarySearch->searchUpcoming(
                    originPhysicalStopUuid: $toUuid,
                    destinationPhysicalStopUuid: $fromUuid,
                    fromDate: $date->addDay(),
                    days: app(OperationsSettings::class)->defaultSearchDays,
                    passengerCount: $passengerCount,
                    sortBy: $sortBy,
                    sortDirection: $sortDirection,
                    serviceClassCode: $serviceClassCode,
                );
            }

            return response()->json([
                'outbound' => array_map(fn ($itinerary): array => $this->serializeItinerary($itinerary), $outbound),
                'inbound' => array_map(fn ($itinerary): array => $this->serializeItinerary($itinerary), $inbound),
            ]);
        }

        $itineraries = $this->itinerarySearch->search(
            originPhysicalStopUuid: $fromUuid,
            destinationPhysicalStopUuid: $toUuid,
            date: $date,
            passengerCount: $passengerCount,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            serviceClassCode: $serviceClassCode,
        );

        return response()->json([
            'data' => array_map(fn ($itinerary): array => $this->serializeItinerary($itinerary), $itineraries),
        ]);
    }

    /**
     * Calculate an aggregated quote for a multi-leg itinerary.
     *
     * Requires a Sanctum bearer token.
     *
     * @authenticated
     *
     * @bodyParam passenger_count integer Number of passengers. Defaults to 1.
     * @bodyParam promo_code string Optional promotion code applied to eligible legs (after dynamic pricing, before group discount). Example: KORA10
     * @bodyParam legs array required Ordered itinerary legs, including a direct itinerary with one leg. For a round trip, include outbound legs first, then inbound legs.
     * @bodyParam legs.*.leg_order integer Leg order, starting at 1.
     * @bodyParam legs.*.departure_uuid string required Departure UUID.
     * @bodyParam legs.*.origin_stop_uuid string required Origin stop UUID.
     * @bodyParam legs.*.destination_stop_uuid string required Destination stop UUID.
     * @bodyParam legs.*.direction string Leg direction: outbound or inbound. Defaults to outbound. Required for round-trip legs.
     *
     * @responseField data.legs.base_amount integer Tarif de base avant application de la tarification dynamique.
     * @responseField data.legs.coefficient number Coefficient multiplicateur appliqué (1.0 = neutre, 1.20 = +20%, 0.90 = -10%).
     * @responseField data.legs.final_amount integer Montant final après tarification dynamique (base_amount × coefficient).
     * @responseField data.legs.dynamic_adjustment_amount integer Écart en XOF entre le prix final et le prix de base (positif = augmentation, négatif = réduction).
     * @responseField data.legs.pricing_rule_uuid string UUID de la règle de tarification dynamique appliquée, ou null si aucune règle n'a matché.
     * @responseField data.legs.fill_rate number Taux de remplissage du segment au moment du devis (0-100).
     * @responseField data.legs.hours_before_departure number Délai en heures entre le devis et le départ (négatif si le départ est passé).
     * @responseField data.pricing.promotion object Promotion appliquée au devis, ou null sans code promo.
     * @responseField data.pricing.promotion.uuid string UUID public de la promotion appliquée.
     * @responseField data.pricing.promotion.code string Code promotionnel appliqué (normalisé en majuscules).
     * @responseField data.pricing.promotion.name string Nom de la promotion.
     * @responseField data.pricing.promotion.discount_amount integer Remise promotionnelle appliquée en XOF.
     * @responseField data.pricing.promotion_discount_amount integer Remise promotionnelle totale en XOF (0 sans code promo).
     *
     * @response 200 scenario="Quote calculated" {
     *   "data": {
     *     "itinerary_quote_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "trip_type": "round_trip",
     *     "total_amount": 24750,
     *     "currency": "XOF",
     *     "expires_at": "2026-09-10T08:00:00Z",
     *     "legs": [
     *     {
     *       "leg_order": 1,
     *       "direction": "outbound",
     *       "quote_uuid": "quote-uuid-1",
     *       "base_amount": 15000,
     *       "coefficient": 1.0,
     *       "final_amount": 15000,
     *       "dynamic_adjustment_amount": 0,
     *       "pricing_rule_uuid": null,
     *       "fill_rate": 25,
     *       "hours_before_departure": 48
     *     },
     *     {
     *       "leg_order": 2,
     *       "direction": "inbound",
     *       "quote_uuid": "quote-uuid-2",
     *       "base_amount": 12500,
     *       "coefficient": 1.0,
     *       "final_amount": 12500,
     *       "dynamic_adjustment_amount": 0,
     *       "pricing_rule_uuid": null,
     *       "fill_rate": 0,
     *       "hours_before_departure": 120
     *     }
     *     ],
     *     "pricing": {
     *       "subtotal_amount": 27500,
     *       "dynamic_adjustment_amount": 0,
     *       "promotion": {
     *         "uuid": "3f9a1c2e-8b7d-4a10-b5c6-2b0d7b3dcb70",
     *         "code": "KORA10",
     *         "name": "Promo lancement",
     *         "discount_amount": 2750
     *       },
     *       "promotion_discount_amount": 2750,
     *       "group_discount": {},
     *       "passenger_allocations": [12375, 12375],
     *       "total_amount": 24750,
     *       "currency": "XOF"
     *     }
     *   }
     * }
     */
    public function quote(ItineraryQuoteRequest $request, CalculateItineraryQuoteAction $calculateQuote, ResolvePayerContextAction $resolvePayer): JsonResponse
    {
        $legs = $this->buildLegQuoteRequests($request);
        $passengerCount = (int) $request->integer('passenger_count', 1);

        $payerData = $request->payer();
        $companyAccount = ($payerData['type'] ?? 'personal') === 'company'
            ? $resolvePayer->handle($request->user(), $payerData)->companyAccount
            : null;

        $result = $calculateQuote->handle(
            $legs,
            $passengerCount,
            $request->user(),
            $request->string('promo_code')->trim()->value() ?: null,
            ReservationChannel::Mobile,
            $companyAccount,
        );

        $tripType = collect($legs)->contains(fn (ItineraryLegQuoteRequest $leg): bool => $leg->direction === 'inbound')
            ? 'round_trip'
            : 'one_way';

        return response()->json(['data' => [
            'itinerary_quote_uuid' => $result->itineraryQuote->uuid,
            'trip_type' => $tripType,
            'total_amount' => $result->totalAmount,
            'currency' => $result->currency,
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'legs' => array_map(fn ($legQuote, $index): array => [
                'leg_order' => $index + 1,
                'direction' => $legs[$index]->direction,
                'quote_uuid' => $legQuote->uuid,
                'base_amount' => (int) $legQuote->base_amount,
                'coefficient' => (float) $legQuote->coefficient,
                'final_amount' => (int) $legQuote->final_amount,
                'dynamic_adjustment_amount' => (int) $legQuote->final_amount - (int) $legQuote->base_amount,
                'pricing_rule_uuid' => $legQuote->snapshot['pricing_rule_uuid'] ?? null,
                'fill_rate' => $legQuote->snapshot['fill_rate_at_quote'] ?? null,
                'hours_before_departure' => $legQuote->snapshot['hours_before_departure_at_quote'] ?? null,
            ], $result->legQuotes, array_keys($result->legQuotes)),
            'pricing' => [
                'subtotal_amount' => $result->snapshot['subtotal_amount'],
                'dynamic_adjustment_amount' => $result->snapshot['dynamic_adjustment_amount'],
                'promotion' => $result->snapshot['promotion'] ?? null,
                'promotion_discount_amount' => $result->snapshot['promotion_discount_amount'] ?? 0,
                'group_discount' => $result->snapshot['group_discount'],
                'company_discount' => $result->snapshot['company_discount'] ?? null,
                'passenger_allocations' => $result->snapshot['passenger_allocations'],
                'total_amount' => $result->totalAmount,
                'currency' => $result->currency,
            ],
        ]]);
    }

    /**
     * Create an atomic seat hold for a multi-leg itinerary.
     *
     * Requires a Sanctum bearer token.
     *
     * @authenticated
     *
     * @bodyParam itinerary_quote_uuid string required Immutable itinerary quote UUID.
     * @bodyParam idempotency_key string required Client-generated key for safe retries.
     * @bodyParam payer object Optional personal or company payer context.
     * @bodyParam passengers array required Passenger-to-leg seat assignment matrix.
     * @bodyParam passengers.*.passenger_index integer required Zero-based passenger index.
     * @bodyParam passengers.*.seat_assignments array required One seat assignment per itinerary leg.
     * @bodyParam passengers.*.seat_assignments.*.leg_order integer required One-based itinerary leg order.
     * @bodyParam passengers.*.seat_assignments.*.seat_uuid string required Seat UUID.
     *
     * @response 201 scenario="Hold created" {
     *   "data": {
     *     "uuid": "reservation-uuid",
     *     "status": "held"
     *   }
     * }
     */
    public function hold(ItineraryHoldRequest $request, CreateItineraryHoldAction $createHold, ResolvePayerContextAction $resolvePayer): JsonResponse
    {
        $quote = ItineraryQuote::query()
            ->where('uuid', $request->string('itinerary_quote_uuid')->toString())
            ->firstOrFail();

        $reservation = $createHold->handle(
            user: $request->user(),
            itineraryQuote: $quote,
            seatAssignments: $request->seatAssignments(),
            channel: ReservationChannel::Mobile,
            idempotencyKey: $request->string('idempotency_key')->toString(),
            payer: $resolvePayer->handle($request->user(), $request->payer()),
        );

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function serializeItinerary(ItineraryData $itinerary): array
    {
        return [
            'connections_count' => $itinerary->connectionsCount,
            'total_amount' => $itinerary->totalAmount,
            'currency' => $itinerary->currency,
            'departure_at' => $itinerary->departureAt->toIso8601String(),
            'arrival_at' => $itinerary->arrivalAt->toIso8601String(),
            'connection_margin_minutes' => $itinerary->connectionMarginMinutes,
            'legs' => array_map(fn ($leg): array => $this->serializeLeg($leg), $itinerary->legs),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeLeg(ItineraryLegData $leg): array
    {
        $bus = $leg->bus ?? $leg->departure->bus;

        return [
            'leg_order' => $leg->legOrder,
            'departure_uuid' => $leg->departure->uuid,
            'line' => [
                'uuid' => $leg->departure->line->uuid,
                'code' => $leg->departure->line->code,
                'name' => $leg->departure->line->name,
                'estimated_duration_minutes' => $leg->estimatedDurationMinutes,
                'is_international' => is_array($leg->departure->line->countries_crossed)
                    && count($leg->departure->line->countries_crossed) > 1,
            ],
            'bus' => $bus !== null ? [
                'uuid' => $bus->uuid,
                'registration_number' => $bus->registration_number,
                'brand' => $bus->brand,
                'model' => $bus->model,
                'seat_count' => $bus->seat_count,
                'amenities' => $bus->amenities->map(fn ($amenity) => [
                    'code' => $amenity->code,
                    'label' => $amenity->label,
                    'icon' => $amenity->icon,
                ])->values()->all(),
            ] : null,
            'origin_stop' => $this->serializeStop($leg->originStop),
            'destination_stop' => $this->serializeStop($leg->destinationStop),
            'amount' => $leg->amount,
            'base_amount' => $leg->baseAmount,
            'coefficient' => $leg->coefficient,
            'final_amount' => $leg->finalAmount,
            'dynamic_adjustment_amount' => $leg->dynamicAdjustmentAmount,
            'pricing_rule_uuid' => $leg->pricingRuleUuid,
            'fill_rate' => $leg->fillRate,
            'hours_before_departure' => $leg->hoursBeforeDeparture,
            'available_seats_count' => $leg->availableSeatsCount,
            'service_class_code' => $leg->serviceClassCode,
            'service_class_name' => $leg->serviceClassName,
            'departure_at' => $leg->departureAt->toIso8601String(),
            'arrival_at' => $leg->arrivalAt->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeStop(Stop $stop): array
    {
        return [
            'uuid' => $stop->uuid,
            'name' => $stop->name,
            'city' => $stop->city,
            'country' => $stop->country,
            'stop_order' => $stop->stop_order,
        ];
    }

    /**
     * @return list<ItineraryLegQuoteRequest>
     */
    private function buildLegQuoteRequests(ItineraryQuoteRequest $request): array
    {
        $legsData = $request->input('legs', []);
        $legs = [];

        foreach ($legsData as $legData) {
            $legs[] = new ItineraryLegQuoteRequest(
                departure: Departure::query()->where('uuid', $legData['departure_uuid'])->firstOrFail(),
                originStop: Stop::query()->where('uuid', $legData['origin_stop_uuid'])->firstOrFail(),
                destinationStop: Stop::query()->where('uuid', $legData['destination_stop_uuid'])->firstOrFail(),
                direction: $legData['direction'] ?? 'outbound',
            );
        }

        return $legs;
    }
}
