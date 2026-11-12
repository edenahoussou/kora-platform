<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kkiapay\Kkiapay;
use Throwable;

/**
 * KKiaPay aggregator adapter (widget flow).
 *
 * KKiaPay has no server-side push: initiate() returns the client widget
 * configuration, the client submits the resulting transactionId, and
 * verify() confirms it through the official KKiaPay PHP SDK.
 *
 * @see https://docs.kkiapay.me/v1/plugin-et-sdk/sdk-javascript
 */
final class KKiaPayAdapter implements PaymentProvider
{
    public function __construct(
        private readonly Kkiapay $sdk,
        private readonly string $publicKey,
        private readonly string $environment,
        private readonly ?string $callbackUrl = null,
        private readonly ?string $webhookSecret = null,
        /** @var list<string> */
        private readonly array $countries = ['BJ'],
    ) {}

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'SUCCESS' => PaymentStatus::Confirmed,
            'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        return new ProviderResponse(
            status: PaymentStatus::Pending,
            payload: [
                'widget' => array_filter([
                    'key' => $this->publicKey,
                    'sandbox' => $this->environment !== 'production',
                    'amount' => $payment->provider_amount ?? $payment->amount,
                    'partnerId' => $payment->uuid,
                    'paymentmethod' => $payment->reservation_id === null
                        ? [($payload['method'] ?? null) === 'card' ? 'card' : 'momo']
                        : ['momo', 'card', 'wallet'],
                    'countries' => $this->countries,
                    'callback' => $this->callbackUrl,
                    'phone' => $payload['phone'] ?? null,
                    'data' => $payment->providerDescription(),
                ], fn ($value): bool => $value !== null),
            ],
            message: 'Ouvrez le widget KKiaPay avec cette configuration.',
        );
    }

    public function verify(Payment $payment): ProviderResponse
    {
        $transactionId = $payment->provider_transaction_id;

        if ($transactionId === null) {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                message: 'En attente du transactionId retourné par le widget KKiaPay.',
            );
        }

        try {
            // The SDK returns a decoded transaction object, or the HTTP
            // status code as int after three failed attempts.
            $result = $this->sdk->verifyTransaction($transactionId);

            if (! is_object($result)) {
                Log::error('KKiaPay status check failed', [
                    'payment_uuid' => $payment->uuid,
                    'result' => $result,
                ]);

                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    payload: ['error' => $result],
                    message: 'Transaction KKiaPay introuvable ou indisponible.',
                );
            }

            /** @var array<string, mixed> $data */
            $data = (array) $result;

            $partnerId = (string) ($data['partnerId'] ?? '');
            if ($partnerId !== '' && $partnerId !== $payment->uuid) {
                Log::warning('KKiaPay partnerId mismatch', [
                    'payment_uuid' => $payment->uuid,
                    'partner_id' => $partnerId,
                ]);

                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    payload: $data,
                    message: 'La transaction ne correspond pas à ce paiement.',
                );
            }

            $status = (string) ($data['status'] ?? 'PENDING');

            return new ProviderResponse(
                status: $this->mapStatus($status),
                providerReference: $transactionId,
                providerTransactionId: $transactionId,
                payload: $data,
                message: "KKiaPay status: {$status}",
            );
        } catch (Throwable $e) {
            Log::error('KKiaPay verify failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                payload: [],
                message: 'Failed to query KKiaPay transaction.',
            );
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        // The x-kkiapay-secret header carries the hash configured on the
        // merchant dashboard webhook, not an API signature.
        $secret = $request->header('x-kkiapay-secret');

        if ($this->webhookSecret === null) {
            Log::warning('KKiaPay webhook secret not configured');

            return null;
        }

        if ($secret === null || ! hash_equals($this->webhookSecret, $secret)) {
            Log::warning('KKiaPay webhook secret mismatch');

            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        if ($payload === null || ! isset($payload['transactionId'])) {
            Log::warning('KKiaPay webhook invalid payload');

            return null;
        }

        return $payload;
    }

    public function supports(string $method, string $provider): bool
    {
        if ($provider !== 'kkiapay') {
            return false;
        }

        return in_array($method, ['mobile_money', 'card', 'wallet'], true);
    }

    public function name(): string
    {
        return 'kkiapay';
    }
}
