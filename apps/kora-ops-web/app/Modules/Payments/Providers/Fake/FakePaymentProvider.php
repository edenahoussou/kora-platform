<?php

namespace App\Modules\Payments\Providers\Fake;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;

/**
 * Fake payment provider for testing.
 *
 * Simulates provider responses without making real API calls.
 * The next status can be controlled via the static $nextStatus property.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public static PaymentStatus $nextStatus = PaymentStatus::Pending;

    public static ?string $nextReference = 'FAKE-REF-123';

    /** @var array<string, mixed>|null */
    public static ?array $webhookPayload = null;

    public static bool $webhookValid = true;

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        return new ProviderResponse(
            status: PaymentStatus::Pending,
            providerReference: self::$nextReference,
            payload: ['fake' => true, 'amount' => $payment->amount],
            message: 'Fake provider initiated.',
        );
    }

    public function confirm(Payment $payment): ProviderResponse
    {
        return new ProviderResponse(
            status: self::$nextStatus,
            providerReference: self::$nextReference,
            payload: ['fake' => true, 'status' => self::$nextStatus->value],
            message: 'Fake provider status: '.self::$nextStatus->value,
        );
    }

    public function verifyWebhook(Request $request): ?array
    {
        if (! self::$webhookValid) {
            return null;
        }

        if (self::$webhookPayload !== null) {
            return self::$webhookPayload;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        return $payload;
    }

    public function supports(string $method, string $provider): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'fake';
    }

    /**
     * Reset the fake provider state between tests.
     */
    public static function reset(): void
    {
        self::$nextStatus = PaymentStatus::Pending;
        self::$nextReference = 'FAKE-REF-123';
        self::$webhookPayload = null;
        self::$webhookValid = true;
    }
}
