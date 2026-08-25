<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Http\Requests\ReleaseSeatHoldRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * @group Bus
 *
 * Reservation lifecycle endpoints for the passenger booking flow.
 */
class ReservationController extends Controller
{
    /**
     * Retrieve a reservation and its current booking state.
     *
     * This endpoint is safe to call after a mobile session is restored. It returns the
     * server-side reservation state instead of requiring the client to rebuild it locally.
     *
     * @authenticated
     *
     * @response 200 scenario="Reservation details" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "held",
     *     "expires_at": "2026-08-20T08:15:00Z",
     *     "total_amount": 25000,
     *     "currency": "XOF",
     *     "channel": "mobile",
     *     "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "price_quote_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f",
     *     "passenger_count": 2,
     *     "segments": [],
     *     "passengers": []
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $reservation);

        $reservation->load([
            'departure.line',
            'priceQuote',
            'segments.seat',
            'segments.originStop',
            'segments.destinationStop',
            'passengers.segments.seat',
        ]);

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Release a held reservation and make its seats available again.
     *
     * The operation is explicit and idempotent for a reservation that is no longer held.
     * It never releases a paid or ticketed reservation.
     *
     * @authenticated
     *
     * @response 204 scenario="Hold released"
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function destroy(
        ReleaseSeatHoldRequest $request,
        Reservation $reservation,
        ReleaseSeatHoldAction $releaseSeatHold,
    ): Response {
        $releaseSeatHold->handle($reservation, $request->user());

        return response()->noContent();
    }
}
