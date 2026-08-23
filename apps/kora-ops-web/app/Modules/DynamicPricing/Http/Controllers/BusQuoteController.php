<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Http\Requests\StoreQuoteRequest;
use App\Modules\DynamicPricing\Http\Resources\PriceQuoteResource;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Http\JsonResponse;

/**
 * @group Bus
 *
 * Fixed price quote endpoint for passenger booking.
 */
class BusQuoteController extends Controller
{
    /**
     * Create a fixed price quote.
     *
     * @response 201 scenario="Quote created" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
     *     "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "base_amount": 12500,
     *     "coefficient": 1.0,
     *     "final_amount": 12500,
     *     "currency": "XOF",
     *     "passenger_count": 1,
     *     "total_amount": 12500,
     *     "expires_at": "2026-08-20T08:10:00Z",
     *     "snapshot": {
     *       "fare_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f",
     *       "line_code": "L2",
     *       "origin_stop": "Cotonou · St Michel",
     *       "destination_stop": "Malanville",
     *       "service_class": "Standard",
     *       "passenger_count": 1,
     *       "total_amount": 12500
     *     }
     *   }
     * }
     */
    public function store(StoreQuoteRequest $request, CalculateQuoteAction $calculateQuote): JsonResponse
    {
        $data = $request->quoteData();

        $departure = Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail();
        $originStop = Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail();
        $destinationStop = Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail();

        $serviceClass = $data['service_class_uuid']
            ? ServiceClass::query()->where('uuid', $data['service_class_uuid'])->first()
            : null;

        $quote = $calculateQuote->handle(
            departure: $departure,
            originStop: $originStop,
            destinationStop: $destinationStop,
            serviceClass: $serviceClass,
            passengerCount: $data['passenger_count'],
        );

        $quote->loadMissing('departure');

        return (new PriceQuoteResource($quote))
            ->response()
            ->setStatusCode(201);
    }
}
