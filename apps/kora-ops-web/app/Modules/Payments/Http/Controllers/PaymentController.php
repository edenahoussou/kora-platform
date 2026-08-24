<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Actions\InitiatePaymentAction;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Payments
 *
 * Payment lifecycle endpoints: initiation, polling status, and provider webhook confirmation.
 */
class PaymentController extends Controller
{
    /**
     * Initiate a payment for a held reservation.
     *
     * @response 201 scenario="Payment initiated" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80",
     *     "status": "pending",
     *     "amount": 12500,
     *     "currency": "XOF",
     *     "provider": "mtn_momo",
     *     "provider_reference": "MM-98270341",
     *     "expires_at": "2026-08-20T08:15:00Z",
     *     "polling_interval_seconds": 10
     *   }
     * }
     */
    public function initiate(
        InitiatePaymentRequest $request,
        InitiatePaymentAction $initiatePayment,
    ): JsonResponse {
        $data = $request->paymentData();

        $reservation = Reservation::query()->where('uuid', $data['reservation_uuid'])->firstOrFail();

        $result = $initiatePayment->handle(
            reservation: $reservation,
            user: $request->user(),
            method: $data['method'],
            provider: $data['provider'],
            idempotencyKey: $data['idempotency_key'],
            phone: $data['phone'],
            walletAmount: $data['wallet_amount'],
            pointsAmount: $data['points_amount'],
        );

        $statusCode = $result['is_replay'] ? 200 : 201;

        return (new PaymentResource($result['payment']))
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * Get payment status (polling).
     *
     * @response 200 scenario="Payment status" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80",
     *     "status": "confirmed",
     *     "amount": 12500,
     *     "currency": "XOF",
     *     "provider": "mtn_momo",
     *     "provider_reference": "MM-98270341",
     *     "confirmed_at": "2026-08-20T08:05:00Z",
     *     "reservation": {
     *       "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70",
     *       "status": "paid",
     *       "departure_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e"
     *     }
     *   }
     * }
     */
    public function show(Payment $payment, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $payment->reservation && $payment->reservation->user_id && $payment->reservation->user_id !== $user->id) {
            throw new AuthorizationException('Vous n\'avez pas accès à ce paiement.');
        }

        return (new PaymentResource($payment->loadMissing(['reservation.departure'])))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Provider webhook / callback to confirm or fail a payment.
     *
     * @unauthenticated
     */
    public function confirm(
        Request $request,
        Payment $payment,
        ConfirmPaymentAction $confirmPayment,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:success,confirmed,failed'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'provider_payload' => ['nullable', 'array'],
        ]);

        $updatedPayment = $confirmPayment->handle(
            payment: $payment,
            status: $validated['status'],
            providerReference: $validated['provider_reference'] ?? null,
            providerPayload: $validated['provider_payload'] ?? [],
        );

        return response()->json([
            'status' => $updatedPayment->status->value,
            'payment_uuid' => $updatedPayment->uuid,
            'reservation_uuid' => $updatedPayment->reservation?->uuid,
            'reservation_status' => $updatedPayment->reservation?->status->value,
        ]);
    }
}
