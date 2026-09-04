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
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
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
     * @queryParam from_physical_stop_uuid string required UUID of the origin physical stop.
     * @queryParam to_physical_stop_uuid string required UUID of the destination physical stop.
     * @queryParam date string required Travel date (Y-m-d).
     *
     * @response 200 scenario="Itineraries found" {
     *   "data": [
     *     {
     *       "connections_count": 1,
     *       "total_amount": 27500,
     *       "currency": "XOF",
     *       "departure_at": "2026-09-10T06:00:00Z",
     *       "arrival_at": "2026-09-10T19:30:00Z",
     *       "connection_margin_minutes": 45,
     *       "legs": [
     *         {
     *           "leg_order": 1,
     *           "departure_uuid": "dep-l1-uuid",
     *           "line_code": "L1",
     *           "origin_stop_uuid": "stop-malanville-uuid",
     *           "destination_stop_uuid": "stop-cotonou-gare-uuid",
     *           "departure_at": "2026-09-10T06:00:00Z",
     *           "arrival_at": "2026-09-10T13:00:00Z",
     *           "amount": 15000
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function index(ItinerarySearchRequest $request): JsonResponse
    {
        $itineraries = $this->itinerarySearch->search(
            originPhysicalStopUuid: $request->string('from_physical_stop_uuid')->toString(),
            destinationPhysicalStopUuid: $request->string('to_physical_stop_uuid')->toString(),
            date: CarbonImmutable::parse($request->string('date')),
            passengerCount: (int) $request->integer('passenger_count', 1),
        );

        return response()->json([
            'data' => array_map(fn ($itinerary): array => $this->serializeItinerary($itinerary), $itineraries),
        ]);
    }

    /**
     * Calculate an aggregated quote for a multi-leg itinerary.
     *
     * @authenticated
     *
     * @response 200 scenario="Quote calculated" {
     *   "total_amount": 27500,
     *   "currency": "XOF",
     *   "expires_at": "2026-09-10T08:00:00Z",
     *   "legs": [
     *     {
     *       "leg_order": 1,
     *       "quote_uuid": "quote-uuid-1",
     *       "final_amount": 15000
     *     }
     *   ]
     * }
     */
    public function quote(ItineraryQuoteRequest $request, CalculateItineraryQuoteAction $calculateQuote): JsonResponse
    {
        $legs = $this->buildLegQuoteRequests($request);
        $passengerCount = (int) $request->integer('passenger_count', 1);

        $result = $calculateQuote->handle($legs, $passengerCount, $request->user());

        return response()->json([
            'total_amount' => $result->totalAmount,
            'currency' => $result->currency,
            'itinerary_quote_uuid' => $result->itineraryQuote->uuid,
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'legs' => array_map(fn ($legQuote): array => [
                'leg_order' => array_search($legQuote, $result->legQuotes, true) + 1,
                'quote_uuid' => $legQuote->uuid,
                'final_amount' => $legQuote->final_amount,
            ], $result->legQuotes),
            'snapshot' => $result->snapshot,
            'pricing' => [
                'subtotal_amount' => $result->snapshot['subtotal_amount'],
                'dynamic_adjustment_amount' => $result->snapshot['dynamic_adjustment_amount'],
                'group_discount' => $result->snapshot['group_discount'],
                'passenger_allocations' => $result->snapshot['passenger_allocations'],
                'total_amount' => $result->totalAmount,
                'currency' => $result->currency,
            ],
        ]);
    }

    /**
     * Create an atomic seat hold for a multi-leg itinerary.
     *
     * @authenticated
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
            'legs' => array_map(fn ($leg): array => [
                'leg_order' => $leg->legOrder,
                'departure_uuid' => $leg->departure->uuid,
                'line_code' => $leg->departure->line->code,
                'origin_stop_uuid' => $leg->originStop->uuid,
                'destination_stop_uuid' => $leg->destinationStop->uuid,
                'departure_at' => $leg->departureAt->toIso8601String(),
                'arrival_at' => $leg->arrivalAt->toIso8601String(),
                'amount' => $leg->amount,
            ], $itinerary->legs),
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
            );
        }

        return $legs;
    }
}
