<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\ItineraryLegRequest;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Http\Requests\ItineraryHoldRequest;
use App\Modules\Booking\Http\Requests\ItineraryQuoteRequest;
use App\Modules\Booking\Http\Requests\ItinerarySearchRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Services\ItineraryData;
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

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
     * @authenticated
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
        Gate::authorize('viewAny', Departure::class);

        $itineraries = $this->itinerarySearch->search(
            originPhysicalStopUuid: $request->string('from_physical_stop_uuid')->toString(),
            destinationPhysicalStopUuid: $request->string('to_physical_stop_uuid')->toString(),
            date: CarbonImmutable::parse($request->string('date')),
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

        $result = $calculateQuote->handle($legs, $passengerCount);

        return response()->json([
            'total_amount' => $result->totalAmount,
            'currency' => $result->currency,
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'legs' => array_map(fn ($legQuote): array => [
                'leg_order' => array_search($legQuote, $result->legQuotes, true) + 1,
                'quote_uuid' => $legQuote->uuid,
                'final_amount' => $legQuote->final_amount,
            ], $result->legQuotes),
            'snapshot' => $result->snapshot,
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
    public function hold(ItineraryHoldRequest $request, CreateItineraryHoldAction $createHold): JsonResponse
    {
        $legs = $this->buildLegRequests($request);

        $reservation = $createHold->handle(
            user: $request->user(),
            legs: $legs,
            totalAmount: (int) $request->integer('total_amount'),
            currency: $request->string('currency', 'XOF')->toString(),
            channel: ReservationChannel::Mobile,
            totalSnapshot: $request->input('snapshot'),
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

    /**
     * @return list<ItineraryLegRequest>
     */
    private function buildLegRequests(ItineraryHoldRequest $request): array
    {
        $legsData = $request->input('legs', []);
        $legs = [];

        foreach ($legsData as $legData) {
            $legs[] = new ItineraryLegRequest(
                departure: Departure::query()->where('uuid', $legData['departure_uuid'])->firstOrFail(),
                originStop: Stop::query()->where('uuid', $legData['origin_stop_uuid'])->firstOrFail(),
                destinationStop: Stop::query()->where('uuid', $legData['destination_stop_uuid'])->firstOrFail(),
                seatUuid: $legData['seat_uuid'],
            );
        }

        return $legs;
    }
}
