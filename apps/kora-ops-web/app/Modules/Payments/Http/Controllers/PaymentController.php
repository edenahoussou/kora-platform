<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Actions\CancelPaymentAction;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Actions\InitiatePaymentAction;
use App\Modules\Payments\Actions\VerifyPaymentAction;
use App\Modules\Payments\Http\Requests\InitiatePaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
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
            mobileMoneyNetwork: $data['mobile_money_network'],
        );

        $statusCode = $result['is_replay'] ? 200 : 201;

        return (new PaymentResource($result['payment']))
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * Return the active payment methods and providers configured for the mobile app.
     */
    public function methods(PaymentManager $manager): JsonResponse
    {
        return response()->json(['data' => $manager->catalog()]);
    }

    /**
     * Explicitly verify a pending payment with its provider.
     */
    public function verify(Payment $payment, Request $request, VerifyPaymentAction $verifyPayment): JsonResponse
    {
        $updatedPayment = $verifyPayment->handle($payment, $request->user());

        return (new PaymentResource($updatedPayment))
            ->response()
            ->setStatusCode(200);
    }

    public function cancel(Payment $payment, Request $request, CancelPaymentAction $cancelPayment): JsonResponse
    {
        $updatedPayment = $cancelPayment->handle($payment, $request->user());

        return (new PaymentResource($updatedPayment))
            ->response()
            ->setStatusCode(200);
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
        if ($user && $payment->reservation && $payment->reservation->created_by_user_id
            && $payment->reservation->created_by_user_id !== $user->id
            && $payment->reservation->payer_user_id !== $user->id) {
            throw new AuthorizationException('Vous n\'avez pas accès à ce paiement.');
        }

        return (new PaymentResource($payment->loadMissing(['reservation.segments.departure'])))
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
        PaymentManager $manager,
    ): JsonResponse {
        $provider = $payment->provider;

        // Verify webhook signature via the provider adapter.
        if ($manager->supports($provider)) {
            $adapter = $manager->resolve($provider);
            $verifiedPayload = $adapter->verifyWebhook($request);

            if ($verifiedPayload === null) {
                return response()->json(['error' => 'Invalid webhook signature.'], 403);
            }

            // Normalize the provider payload into KORA status.
            $providerResponse = $adapter->verify($payment);

            $updatedPayment = $confirmPayment->handle(
                payment: $payment,
                status: $providerResponse->isConfirmed() ? 'confirmed' : ($providerResponse->isFailed() ? 'failed' : 'pending'),
                providerReference: $providerResponse->providerReference,
                providerPayload: $providerResponse->payload,
            );

            return response()->json([
                'status' => $updatedPayment->status->value,
                'payment_uuid' => $updatedPayment->uuid,
                'reservation_uuid' => $updatedPayment->reservation?->uuid,
                'reservation_status' => $updatedPayment->reservation?->status->value,
            ]);
        }

        // Fallback for manual/test confirmations (no real provider).
        abort_unless(app()->environment(['local', 'testing']), 403, 'Manual payment confirmation is disabled outside local and testing environments.');

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

    /**
     * FedaPay webhook endpoint.
     *
     * @unauthenticated
     */
    public function webhookFedaPay(
        Request $request,
        PaymentManager $manager,
        ConfirmPaymentAction $confirmPayment,
    ): JsonResponse {
        return $this->processProviderWebhook($request, $manager, $confirmPayment, 'fedapay');
    }

    /**
     * MTN MoMo webhook endpoint.
     *
     * @unauthenticated
     */
    public function webhookMtnMomo(
        Request $request,
        PaymentManager $manager,
        ConfirmPaymentAction $confirmPayment,
    ): JsonResponse {
        return $this->processProviderWebhook($request, $manager, $confirmPayment, 'mtn_momo');
    }

    /**
     * Process a provider webhook by verifying the signature and confirming the payment.
     */
    private function processProviderWebhook(
        Request $request,
        PaymentManager $manager,
        ConfirmPaymentAction $confirmPayment,
        string $providerKey,
    ): JsonResponse {
        $adapter = $manager->resolve($providerKey);
        $verifiedPayload = $adapter->verifyWebhook($request);

        if ($verifiedPayload === null) {
            return response()->json(['error' => 'Invalid webhook signature.'], 403);
        }

        // Extract the payment reference from the webhook payload.
        $paymentUuid = $verifiedPayload['custom_metadata']['kora_payment_uuid']
            ?? $verifiedPayload['externalId']
            ?? null;

        if ($paymentUuid === null) {
            return response()->json(['error' => 'Payment reference not found in webhook.'], 400);
        }

        $payment = Payment::query()->where('uuid', $paymentUuid)->first();

        if ($payment === null) {
            return response()->json(['error' => 'Payment not found.'], 404);
        }

        $providerResponse = $adapter->verify($payment);

        $updatedPayment = $confirmPayment->handle(
            payment: $payment,
            status: $providerResponse->isConfirmed() ? 'confirmed' : ($providerResponse->isFailed() ? 'failed' : 'pending'),
            providerReference: $providerResponse->providerReference,
            providerPayload: $providerResponse->payload,
        );

        return response()->json([
            'status' => $updatedPayment->status->value,
            'payment_uuid' => $updatedPayment->uuid,
            'reservation_uuid' => $updatedPayment->reservation?->uuid,
            'reservation_status' => $updatedPayment->reservation?->status->value,
        ]);
    }
}
