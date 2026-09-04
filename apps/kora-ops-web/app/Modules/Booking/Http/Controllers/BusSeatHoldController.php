<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\CreateSeatHoldAction;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Actions\ResolvePayerContextAction;
use App\Modules\Booking\Http\Requests\StoreSeatHoldRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Bus
 *
 * Seat hold endpoints for passenger booking.
 */
class BusSeatHoldController extends Controller
{
    /**
     * Hold seats for 15 minutes.
     *
     * Requires a Sanctum bearer token.
     *
     * @authenticated
     *
     * @response 201 scenario="Seats held" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "held",
     *     "expires_at": "2026-08-20T08:15:00Z",
     *     "total_amount": 12500,
     *     "currency": "XOF",
     *     "channel": "mobile",
     *     "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "segments": [
     *       {
     *         "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb71",
     *         "seat_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb72",
     *         "seat_code": "4A",
     *         "origin_stop": "Cotonou · St Michel",
     *         "destination_stop": "Malanville",
     *         "status": "held"
     *       }
     *     ]
     *   }
     * }
     * @response 409 scenario="Seat unavailable" {
     *   "message": "Le siège 4A n'est plus disponible sur ce segment.",
     *   "conflicting_seat_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb72",
     *   "seat_code": "4A"
     * }
     */
    public function store(StoreSeatHoldRequest $request, CreateSeatHoldAction $createSeatHold, ResolvePayerContextAction $resolvePayer): JsonResponse
    {
        $data = $request->holdData();

        $departure = Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail();
        $originStop = Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail();
        $destinationStop = Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail();
        $priceQuote = PriceQuote::query()->where('uuid', $data['price_quote_uuid'])->firstOrFail();

        $reservation = $createSeatHold->handle(
            user: $request->user(),
            departure: $departure,
            originStop: $originStop,
            destinationStop: $destinationStop,
            priceQuote: $priceQuote,
            seatUuids: $data['seat_uuids'],
            payer: $resolvePayer->handle($request->user(), $data['payer']),
            idempotencyKey: $data['idempotency_key'],
        );

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Release a seat hold.
     *
     * Cancels a held reservation and frees its seats immediately, before the
     * 15-minute hold expiry. Only the user who created the hold may release it.
     * Releasing an already-released or expired hold is a no-op and still
     * returns 204.
     *
     * Requires a Sanctum bearer token.
     *
     * @authenticated
     *
     * @urlParam reservation string required Public UUID of the held reservation. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70
     *
     * @response 204 scenario="Hold released"
     * @response 403 scenario="Reservation belongs to another user" {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Reservation not found" {
     *   "message": "No query results for model [App\\Modules\\Booking\\Models\\Reservation]."
     * }
     */
    public function destroy(Reservation $reservation, ReleaseSeatHoldAction $releaseSeatHold): Response
    {
        $releaseSeatHold->handle($reservation, request()->user());

        return response()->noContent();
    }
}
