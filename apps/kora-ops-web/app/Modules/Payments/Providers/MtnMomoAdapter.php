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
        private readonly string $targetEnvironment = 'mtncameroon',
        private readonly string $currency = 'XAF',
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
            ])->post("{$this->baseUrl()}/collection/token/");

            if (! $response->ok()) {
                Log::error('MTN MoMo token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
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
    private function apiHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'X-Target-Environment' => $this->targetEnvironment,
            'Content-Type' => 'application/json',
        ];
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
        try {
            $referenceId = (string) Str::uuid();

            $body = [
                'amount' => (string) $payment->amount,
                'currency' => $this->currency,
                'externalId' => $payment->uuid,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => (string) ($payload['phone'] ?? ''),
                ],
                'payerMessage' => "KORA BUS reservation {$payment->reservation->uuid}",
                'payeeNote' => "Payment {$payment->uuid}",
            ];

            $response = Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl()}/collection/v1_0/requesttopay", $body);

            // 202 Accepted means the request was accepted for processing.
            if ($response->status() !== 202) {
                Log::error('MTN MoMo requestToPay failed', [
                    'payment_uuid' => $payment->uuid,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return new ProviderResponse(
                    status: PaymentStatus::Failed,
                    payload: ['error' => $response->body()],
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
                'error' => $e->getMessage(),
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Failed,
                payload: ['error' => $e->getMessage()],
                message: 'Failed to initiate MTN MoMo payment.',
            );
        }
    }

    public function confirm(Payment $payment): ProviderResponse
    {
        if ($payment->provider_reference === null) {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                message: 'No provider reference available for polling.',
            );
        }

        try {
            $response = Http::withHeaders($this->apiHeaders())
                ->get("{$this->baseUrl()}/collection/v1_0/requesttopay/{$payment->provider_reference}");

            if (! $response->ok()) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    payload: ['error' => $response->body()],
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
            Log::error('MTN MoMo confirm failed', [
                'payment_uuid' => $payment->uuid,
                'error' => $e->getMessage(),
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                payload: ['error' => $e->getMessage()],
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
