<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Client\Response;
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

    private const CALLBACK_TOKEN_CONTEXT = 'kora:mtn-momo:callback';

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
        private readonly ?string $baseUrl = null,
    ) {}

    /**
     * Get the base URL for the configured environment.
     */
    private function baseUrl(): string
    {
        if ($this->baseUrl !== null && trim($this->baseUrl) !== '') {
            return rtrim($this->baseUrl, '/');
        }

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
     * Cache key scoped by environment and credentials without exposing secrets.
     */
    private function tokenCacheKey(): string
    {
        return 'mtn_momo_access_token:'.hash('sha256', implode('|', [
            $this->baseUrl(),
            $this->targetEnvironment,
            $this->subscriptionKey,
            $this->userId,
            $this->apiKey,
        ]));
    }

    /**
     * Get or refresh the OAuth2 access token from MTN MoMo.
     */
    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(55), function (): string {
            $credentials = base64_encode("{$this->userId}:{$this->apiKey}");

            $response = Http::withHeaders([
                'Authorization' => "Basic {$credentials}",
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'X-Target-Environment' => $this->targetEnvironment,
            ])->timeout(15)->connectTimeout(5)->retry(2, 100)->post("{$this->baseUrl()}/collection/token/");

            if (! $response->ok()) {
                Log::error('MTN MoMo token request failed', [
                    'status' => $response->status(),
                    'environment' => $this->environment,
                    'target_environment' => $this->targetEnvironment,
                ]);

                throw new \RuntimeException('Failed to obtain MTN MoMo access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function forgetAccessToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Standard headers for MTN MoMo API requests.
     *
     * @return array<string, string>
     */
    private function apiHeaders(?string $referenceId = null): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'X-Target-Environment' => $this->targetEnvironment,
            'X-Reference-Id' => $referenceId,
            'X-Callback-Url' => $this->callbackUrlWithToken(),
            'Content-Type' => 'application/json',
        ]);
    }

    /** @param array<string, mixed> $body */
    private function postWithToken(string $url, array $body, ?string $referenceId = null): Response
    {
        $response = Http::withHeaders($this->apiHeaders($referenceId))
            ->timeout(20)
            ->connectTimeout(5)
            ->post($url, $body);

        if ($response->status() === 401) {
            $this->forgetAccessToken();

            $response = Http::withHeaders($this->apiHeaders($referenceId))
                ->timeout(20)
                ->connectTimeout(5)
                ->post($url, $body);
        }

        return $response;
    }

    private function getWithToken(string $url, ?string $referenceId = null): Response
    {
        $response = Http::withHeaders($this->apiHeaders($referenceId))
            ->timeout(15)
            ->connectTimeout(5)
            ->get($url);

        if ($response->status() === 401) {
            $this->forgetAccessToken();

            $response = Http::withHeaders($this->apiHeaders($referenceId))
                ->timeout(15)
                ->connectTimeout(5)
                ->get($url);
        }

        return $response;
    }

    /**
     * Map MTN MoMo transaction status to KORA PaymentStatus.
     */
    private function mapStatus(string $mtnStatus): PaymentStatus
    {
        return match (strtoupper($mtnStatus)) {
            'SUCCESSFUL' => PaymentStatus::Confirmed,
            'FAILED', 'REJECTED', 'TIMEOUT' => PaymentStatus::Failed,
            'CANCELLED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * MTN does not sign callbacks. The callback URL carries an opaque token
     * derived from the configured webhook secret; the header remains accepted
     * for controlled manual tests. The callback is only a signal: status is
     * always re-verified server-side before any business mutation.
     */
    private function callbackToken(): ?string
    {
        if ($this->webhookSecret === null || trim($this->webhookSecret) === '') {
            return null;
        }

        return substr(hash_hmac('sha256', self::CALLBACK_TOKEN_CONTEXT, $this->webhookSecret), 0, 32);
    }

    private function callbackUrlWithToken(): ?string
    {
        if ($this->callbackUrl === null || trim($this->callbackUrl) === '') {
            return null;
        }

        $url = rtrim($this->callbackUrl, '/');
        $token = $this->callbackToken();

        return $token === null ? $url : "{$url}/{$token}";
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        $referenceId = $payment->provider_reference
            ?: ($payment->reservation_id === null ? $payment->uuid : (string) Str::uuid());

        // Persist before the HTTP call so a crash or a lost response can never
        // orphan an accepted MTN transaction.
        if ($payment->provider_reference === null || $payment->provider_reference === '') {
            $payment->provider_reference = $referenceId;
            $payment->save();
        }

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

            $response = $this->postWithToken(
                "{$this->baseUrl()}/collection/v1_0/requesttopay",
                $body,
                $referenceId,
            );

            // 202 Accepted means MTN accepted the request for processing.
            if ($response->status() === 202) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    providerReference: $referenceId,
                    payload: [
                        'reference_id' => $referenceId,
                        'status' => 'PENDING',
                    ],
                    message: 'MTN MoMo requestToPay accepted, awaiting customer approval.',
                );
            }

            // 409 means the reference already exists. Do not fail: verify it.
            if ($response->status() === 409) {
                $verification = $this->verify($payment->fresh());

                return new ProviderResponse(
                    status: $verification->status,
                    providerReference: $referenceId,
                    providerTransactionId: $verification->providerTransactionId,
                    payload: array_merge(
                        ['initiation_issue' => 'reference_conflict'],
                        $verification->payload,
                    ),
                    message: 'MTN MoMo reference already exists; status re-verified.',
                );
            }

            if ($this->isAmbiguousStatus($response->status())) {
                Log::warning('MTN MoMo requestToPay returned an ambiguous status', [
                    'payment_uuid' => $payment->uuid,
                    'status' => $response->status(),
                    'environment' => $this->environment,
                ]);

                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    providerReference: $referenceId,
                    payload: [
                        'initiation_issue' => 'provider_unavailable',
                        'provider_status' => $response->status(),
                    ],
                    message: 'MTN MoMo requestToPay returned an ambiguous status; reconciliation required.',
                );
            }

            Log::error('MTN MoMo requestToPay failed', [
                'payment_uuid' => $payment->uuid,
                'status' => $response->status(),
                'environment' => $this->environment,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Failed,
                providerReference: $referenceId,
                payload: [
                    'initiation_issue' => 'provider_rejected',
                    'provider_status' => $response->status(),
                ],
                message: 'MTN MoMo requestToPay was rejected.',
            );
        } catch (Throwable $e) {
            Log::error('MTN MoMo initiate failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
                'environment' => $this->environment,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $referenceId,
                payload: ['initiation_issue' => 'provider_unavailable'],
                message: 'Failed to initiate MTN MoMo payment; reconciliation required.',
            );
        }
    }

    public function verify(Payment $payment): ProviderResponse
    {
        if ($payment->provider_reference === null || $payment->provider_reference === '') {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                message: 'No provider reference available for polling.',
            );
        }

        try {
            $response = $this->getWithToken(
                "{$this->baseUrl()}/collection/v1_0/requesttopay/{$payment->provider_reference}",
                $payment->provider_reference,
            );

            if (! $response->ok()) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    providerReference: $payment->provider_reference,
                    payload: ['verification_issue' => 'provider_status_unavailable'],
                    message: 'MTN MoMo transaction not yet available.',
                );
            }

            $data = $response->json();
            if (! is_array($data)) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    providerReference: $payment->provider_reference,
                    payload: ['verification_issue' => 'invalid_provider_payload'],
                    message: 'MTN MoMo returned an invalid payload.',
                );
            }

            $mtnStatus = (string) ($data['status'] ?? $data['transactionStatus'] ?? 'PENDING');
            $status = $this->mapStatus($mtnStatus);
            $transactionId = $this->normalizeTransactionId($data['financialTransactionId'] ?? null);

            if (in_array($status, [PaymentStatus::Confirmed, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
                $issue = $this->verificationIssue($payment, $data, $status);

                if ($issue !== null) {
                    Log::warning('MTN MoMo verification mismatch', [
                        'payment_uuid' => $payment->uuid,
                        'provider_status' => $mtnStatus,
                        'issue' => $issue,
                    ]);

                    return new ProviderResponse(
                        status: PaymentStatus::Pending,
                        providerReference: $payment->provider_reference,
                        providerTransactionId: $transactionId,
                        payload: array_merge($data, ['verification_issue' => $issue]),
                        message: 'MTN MoMo payload could not be matched safely to this payment.',
                    );
                }
            }

            return new ProviderResponse(
                status: $status,
                providerReference: $payment->provider_reference,
                providerTransactionId: $transactionId,
                payload: $data,
                message: "MTN MoMo status: {$mtnStatus}",
            );
        } catch (Throwable $e) {
            Log::error('MTN MoMo verify failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
                'environment' => $this->environment,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $payment->provider_reference,
                payload: ['verification_issue' => 'provider_unavailable'],
                message: 'Failed to query MTN MoMo transaction; reconciliation required.',
            );
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        $expected = $this->callbackToken();

        if ($expected === null) {
            Log::warning('MTN MoMo webhook secret not configured');

            return null;
        }

        $provided = $request->route('callbackToken')
            ?? $request->header('X-Kora-Webhook-Token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            Log::warning('MTN MoMo webhook token mismatch', [
                'has_route_token' => $request->route('callbackToken') !== null,
                'has_header_token' => $request->header('X-Kora-Webhook-Token') !== null,
            ]);

            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        if (! is_array($payload)) {
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

    private function isAmbiguousStatus(int $status): bool
    {
        return $status >= 500 || in_array($status, [408, 409, 429], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verificationIssue(Payment $payment, array $data, PaymentStatus $status): ?string
    {
        $externalId = $data['externalId'] ?? null;

        if (! is_string($externalId) || $externalId === '') {
            return 'external_id_missing';
        }

        if ($externalId !== $payment->uuid) {
            return 'external_id_mismatch';
        }

        $currency = data_get($data, 'currency.iso') ?? ($data['currency'] ?? null);
        $rawAmount = $data['amount'] ?? null;
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : null;

        if ($status === PaymentStatus::Confirmed) {
            if ($amount === null) {
                return 'amount_missing';
            }

            if (! is_string($currency) || $currency === '') {
                return 'currency_missing';
            }
        }

        if ($amount !== null && $payment->provider_amount !== null
            && abs($amount - (float) $payment->provider_amount) > 0.0001) {
            return 'amount_mismatch';
        }

        if (is_string($currency) && $currency !== '' && $payment->provider_currency !== null
            && strtoupper($currency) !== strtoupper($payment->provider_currency)) {
            return 'currency_mismatch';
        }

        return null;
    }

    private function normalizeTransactionId(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || strlen($normalized) > 128) {
            return null;
        }

        return $normalized;
    }
}
