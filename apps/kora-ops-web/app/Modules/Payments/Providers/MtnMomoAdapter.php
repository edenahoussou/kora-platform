<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * MTN MoMo (Mobile Money) direct API adapter.
 *
 * Uses the MTN MoMo Collection API to request payments from customer wallets.
 * No official SDK — uses Laravel's HTTP client directly.
 *
 * @see https://momodeveloper.mtn.com/api-documentation
 */
final class MtnMomoAdapter implements PaymentProvider
{
    private const SANDBOX_URL = 'https://sandbox.momodeveloper.mtn.com';

    private const PRODUCTION_URL = 'https://momodeveloper.mtn.com';

    private const CACHE_TOKEN_KEY = 'mtn_momo_access_token';

    public function __construct(
        private readonly string $subscriptionKey,
        private readonly string $userId,
        private readonly string $apiKey,
        private readonly string $environment,
        private readonly string $targetEnvironment = 'mtnbenin',
        private readonly string $sandboxCurrency = 'EUR',
        private readonly string $productionCurrency = 'XOF',
        private readonly ?string $callbackUrl = null,
        private readonly ?string $webhookSecret = null,
    ) {}

    /**
     * Get the base URL for the configured environment.
     */
    private function baseUrl(): string
    {
        return $this->environment === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
    }

    private function configuredCurrency(): string
    {
        return $this->environment === 'production'
            ? $this->productionCurrency
            : $this->sandboxCurrency;
    }

    /**
     * Get or refresh the OAuth2 access token from MTN MoMo.
     */
    private function accessToken(): string
    {
        return Cache::remember(self::CACHE_TOKEN_KEY, now()->addMinutes(55), function (): string {
            $credentials = base64_encode("{$this->userId}:{$this->apiKey}");

            $response = Http::withHeaders([
                'Authorization' => "Basic {$credentials}",
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ])->timeout(15)->connectTimeout(5)->retry(2, 100)->post("{$this->baseUrl()}/collection/token/");

            if (! $response->ok()) {
                Log::error('MTN MoMo token request failed', [
                    'status' => $response->status(),
                ]);

                throw new \RuntimeException('Failed to obtain MTN MoMo access token.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Standard headers for MTN MoMo API requests.
     */
    /**
     * @return array<string, string>
     */
    private function apiHeaders(?string $referenceId = null): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'X-Target-Environment' => $this->targetEnvironment,
            'X-Reference-Id' => $referenceId,
            'X-Callback-Url' => $this->callbackUrl,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Map MTN MoMo transaction status to KORA PaymentStatus.
     */
    private function mapStatus(string $mtnStatus): PaymentStatus
    {
        return match ($mtnStatus) {
            'SUCCESSFUL' => PaymentStatus::Confirmed,
            'FAILED' => PaymentStatus::Failed,
            'REJECTED' => PaymentStatus::Failed,
            'TIMEOUT' => PaymentStatus::Failed,
            'CANCELLED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        $isTopUp = $payment->reservation_id === null;
        $referenceId = $isTopUp ? $payment->uuid : (string) Str::uuid();

        $submitted = false;

        try {
            $body = [
                'amount' => (string) ($payment->provider_amount ?? $payment->amount),
                'currency' => $payment->provider_currency ?? $this->configuredCurrency(),
                'externalId' => $payment->uuid,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => ltrim((string) ($payload['phone'] ?? ''), '+'),
                ],
                'payerMessage' => $payment->providerDescription(),
                'payeeNote' => "Payment {$payment->uuid}",
            ];

            $headers = $this->apiHeaders($referenceId);
            if ($isTopUp) {
                $payment->update(['provider_reference' => $referenceId]);
            }
            $submitted = true;
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->connectTimeout(5)
                ->post("{$this->baseUrl()}/collection/v1_0/requesttopay", $body);

            // 202 Accepted means the request was accepted for processing.
            if ($response->status() !== 202) {
                Log::error('MTN MoMo requestToPay failed', [
                    'payment_uuid' => $payment->uuid,
                    'status' => $response->status(),
                ]);

                return new ProviderResponse(
                    status: $isTopUp && ($response->serverError() || in_array($response->status(), [408, 409, 429], true)) ? PaymentStatus::Pending : PaymentStatus::Failed,
                    providerReference: $referenceId,
                    payload: ['initiation_issue' => 'provider_unavailable'],
                    message: 'MTN MoMo requestToPay was not accepted.',
                );
            }

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $referenceId,
                payload: [
                    'reference_id' => $referenceId,
                    'status' => 'PENDING',
                ],
                message: 'MTN MoMo requestToPay accepted, awaiting customer approval.',
            );
        } catch (Throwable $e) {
            Log::error('MTN MoMo initiate failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
            ]);

            return new ProviderResponse(
                status: $isTopUp && $submitted ? PaymentStatus::Pending : PaymentStatus::Failed,
                providerReference: $submitted ? $referenceId : null,
                payload: ['initiation_issue' => 'provider_unavailable'],
                message: 'Failed to initiate MTN MoMo payment.',
            );
        }
    }

    public function verify(Payment $payment): ProviderResponse
    {
        if ($payment->provider_reference === null) {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                message: 'No provider reference available for polling.',
            );
        }

        try {
            $response = Http::withHeaders($this->apiHeaders($payment->provider_reference))
                ->timeout(15)
                ->connectTimeout(5)
                ->retry(2, 100)
                ->get("{$this->baseUrl()}/collection/v1_0/requesttopay/{$payment->provider_reference}");

            if (! $response->ok()) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    payload: [],
                    message: 'MTN MoMo transaction not yet available.',
                );
            }

            $data = $response->json();
            $mtnStatus = (string) ($data['status'] ?? 'PENDING');

            return new ProviderResponse(
                status: $this->mapStatus($mtnStatus),
                providerReference: $payment->provider_reference,
                payload: $data,
                message: "MTN MoMo status: {$mtnStatus}",
            );
        } catch (Throwable $e) {
            Log::error('MTN MoMo verify failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                payload: [],
                message: 'Failed to query MTN MoMo transaction.',
            );
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        // MTN MoMo does not provide native HMAC webhook signing.
        // We verify via a shared secret token configured in the MTN dashboard
        // and sent in the X-Kora-Webhook-Token header on every callback.
        $token = $request->header('X-Kora-Webhook-Token');

        if ($this->webhookSecret === null) {
            Log::warning('MTN MoMo webhook secret not configured');

            return null;
        }

        if ($token === null || ! hash_equals($this->webhookSecret, $token)) {
            Log::warning('MTN MoMo webhook token mismatch');

            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        if ($payload === null || ! isset($payload['status'])) {
            Log::warning('MTN MoMo webhook invalid payload');

            return null;
        }

        return $payload;
    }

    public function supports(string $method, string $provider): bool
    {
        if ($provider !== 'mtn_momo') {
            return false;
        }

        return $method === 'mobile_money';
    }

    public function name(): string
    {
        return 'mtn_momo';
    }
}
