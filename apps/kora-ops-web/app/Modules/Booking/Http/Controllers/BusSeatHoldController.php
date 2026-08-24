<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\CreateSeatHoldAction;
use App\Modules\Booking\Http\Requests\StoreSeatHoldRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Illuminate\Http\JsonResponse;

/**
 * @group Bus
 *
 * Seat hold endpoint for passenger booking.
 */
class BusSeatHoldController extends Controller
{
    /**
     * Hold seats for 15 minutes.
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
    public function store(StoreSeatHoldRequest $request, CreateSeatHoldAction $createSeatHold): JsonResponse
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
        );

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}
