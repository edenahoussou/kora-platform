<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\UpdateReservationPassengersAction;
use App\Modules\Booking\Http\Requests\StorePassengersRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Http\JsonResponse;

/**
 * @group Bus
 *
 * Passenger information capture for bus reservations.
 */
class ReservationPassengerController extends Controller
{
    /**
     * Store passenger details for a reservation.
     *
     * Requires a Sanctum bearer token.
     *
     * @authenticated
     *
     * @bodyParam passengers array required Passenger identity data, one item per passenger.
     * @bodyParam passengers.*.passenger_index integer Stable zero-based passenger index across all itinerary legs.
     * @bodyParam passengers.*.first_name string required Passenger first name.
     * @bodyParam passengers.*.last_name string required Passenger last name.
     * @bodyParam passengers.*.phone string Passenger phone number.
     * @bodyParam passengers.*.email string Passenger email address.
     * @bodyParam passengers.*.document_type string Identity document type required for international lines.
     * @bodyParam passengers.*.document_number string Identity document number required for international lines.
     * @bodyParam passengers.*.nationality string ISO 2-letter nationality code.
     * @bodyParam passengers.*.birth_date string Date of birth in Y-m-d format.
     * @bodyParam passengers.*.seat_uuid string Seat UUID for the passenger's segment.
     *
     * @response 200 scenario="Passengers stored" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "held",
     *     "expires_at": "2026-08-20T08:15:00Z",
     *     "total_amount": 12500,
     *     "currency": "XOF",
     *     "channel": "mobile",
     *     "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "passengers": [
     *       {
     *         "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb73",
     *         "passenger_index": 0,
     *         "first_name": "Aïcha",
     *         "last_name": "HOUNKPÈ",
     *         "phone": "+2290197421860",
     *         "document_type": "passport",
     *         "document_number": "BJ0427851",
     *         "nationality": "BJ",
     *         "birth_date": "1994-04-18",
     *         "seat_code": "4A"
     *       }
     *     ]
     *   }
     * }
     */
    public function store(
        StorePassengersRequest $request,
        Reservation $reservation,
        UpdateReservationPassengersAction $updatePassengers,
    ): JsonResponse {
        $updatedReservation = $updatePassengers->handle(
            reservation: $reservation,
            user: $request->user(),
            passengersData: $request->passengersData(),
        );

        return (new ReservationResource($updatedReservation))
            ->response()
            ->setStatusCode(200);
    }
}
