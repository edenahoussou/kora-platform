<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Actions\ApplyReservationPromoCodeAction;
use App\Modules\Booking\Actions\CancelReservationAction;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Http\Requests\ReleaseSeatHoldRequest;
use App\Modules\Booking\Http\Requests\UpdateReservationPromoCodeRequest;
use App\Modules\Booking\Http\Resources\ReservationResource;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
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
            'segments.departure.line',
            'itineraryQuote',
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
     * Apply or remove a promo code on a held reservation.
     *
     * Lets the passenger enter (or clear) a promo code on the payment screen,
     * after the quote and hold are already created. The reservation total is
     * recalculated in place — seats and passengers are untouched. Only allowed
     * while the reservation is still held and no payment is in progress. The
     * promotion usage is recorded only once the payment is confirmed.
     *
     * @authenticated
     *
     * @urlParam reservation_uuid string required Public UUID of the reservation. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70
     *
     * @bodyParam promo_code string required The promo code to apply (max 32 chars). Must be present in the body — send `null` to remove the current discount. Example: TESTNE10
     *
     * @response 200 scenario="Promo code applied" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "held",
     *     "total_amount": 8100,
     *     "currency": "XOF",
     *     "promotion_discount_amount": 900,
     *     "promotion": {
     *       "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6a",
     *       "code": "TESTNE10",
     *       "name": "Lancement Nord-Est",
     *       "discount_amount": 900
     *     }
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Promo code rejected" {
     *   "message": "Ce code promotionnel est inconnu.",
     *   "errors": {
     *     "promo_code": ["Ce code promotionnel est inconnu."]
     *   }
     * }
     */
    public function updatePromoCode(
        UpdateReservationPromoCodeRequest $request,
        Reservation $reservation,
        ApplyReservationPromoCodeAction $applyPromoCode,
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('updatePromoCode', $reservation);

        return (new ReservationResource($applyPromoCode->handle($reservation, $request->promoCode())))
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

    /**
     * Cancel a reservation and release its held seats.
     *
     * Only reservations that are still held (not paid or ticketed) can be cancelled.
     * The operation is idempotent — cancelling an already-cancelled reservation is a no-op.
     *
     * @authenticated
     *
     * @response 200 scenario="Reservation cancelled" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "cancelled",
     *     "total_amount": 25000,
     *     "currency": "XOF",
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
    public function cancel(Request $request, Reservation $reservation, CancelReservationAction $cancelReservation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('cancel', $reservation);

        return (new ReservationResource($cancelReservation->cancel($reservation, $request->user())))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Cancel a single passenger within a group reservation.
     *
     * Releases only the segments associated with the specified passenger, keeping the
     * rest of the reservation intact. The passenger must belong to the reservation.
     *
     * @authenticated
     *
     * @response 200 scenario="Passenger cancelled" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *     "status": "held",
     *     "total_amount": 12500,
     *     "currency": "XOF",
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
     * @response 404 scenario="Passenger not found" {
     *   "message": "No query results for model [App\\Modules\\Booking\\Models\\ReservationPassenger]."
     * }
     */
    public function cancelPassenger(
        Request $request,
        Reservation $reservation,
        ReservationPassenger $passenger,
        CancelReservationAction $cancelReservation,
    ): JsonResponse {
        Gate::forUser($request->user())->authorize('cancel', $reservation);
        abort_unless($passenger->reservation_id === $reservation->getKey(), 404);

        return (new ReservationResource($cancelReservation->cancelPassenger($reservation, $passenger, $request->user())))
            ->response()
            ->setStatusCode(200);
    }
}
