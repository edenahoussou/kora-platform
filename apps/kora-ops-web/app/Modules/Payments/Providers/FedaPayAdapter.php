<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use FedaPay\Currency;
use FedaPay\Error\Base;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * FedaPay payment provider adapter.
 *
 * Uses the official fedapay/fedapay-php SDK to create transactions,
 * retrieve their status, and verify webhook signatures.
 */
final class FedaPayAdapter implements PaymentProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $environment,
        private readonly ?string $callbackUrl,
        private readonly ?string $webhookSecret = null,
        private readonly string $defaultPhoneCountry = 'bj',
        /** @var array<string, string> */
        private readonly array $mobileMoneyModes = [],
    ) {}

    /**
     * Create a new FedaPay instance with configured credentials.
     */
    private function configure(): void
    {
        FedaPay::setApiKey($this->apiKey);
        FedaPay::setEnvironment($this->environment);
    }

    /**
     * Map FedaPay transaction status to KORA PaymentStatus.
     */
    private function mapStatus(string $fedapayStatus): PaymentStatus
    {
        return match ($fedapayStatus) {
            'approved', 'transferred' => PaymentStatus::Confirmed,
            'declined' => PaymentStatus::Failed,
            'canceled', 'cancelled' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Extract phone number and country from payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{number: string, country: string}
     */
    private function extractPhone(array $payload): array
    {
        $phone = (string) ($payload['phone'] ?? '');
        $network = MobileMoneyNetwork::tryFrom((string) ($payload['mobile_money_network'] ?? ''));
        $country = (string) ($payload['phone_country'] ?? $network?->country() ?? $this->defaultPhoneCountry);

        return ['number' => $phone, 'country' => $country];
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        $isTopUp = $payment->reservation_id === null;
        $method = $payload['method'] ?? null;
        $phone = $this->extractPhone($payload);
        $mode = $this->mobileMoneyModes[(string) ($payload['mobile_money_network'] ?? '')] ?? null;
        if ($method === 'mobile_money' && ($mode === null || $phone['number'] === '')) {
            return new ProviderResponse(
                status: PaymentStatus::Failed,
                payload: ['initiation_issue' => 'invalid_payment_details'],
            );
        }

        $transactionId = $payment->provider_transaction_id;
        $reference = $payment->provider_reference;

        try {
            $this->configure();

            if ($transactionId !== null && $isTopUp) {
                $transaction = $this->requireTransaction(Transaction::retrieve($transactionId));
            } else {
                $transactionData = [
                    'description' => $payment->providerDescription(),
                    'amount' => $payment->provider_amount ?? $payment->amount,
                    'currency' => ['iso' => $payment->provider_currency ?? $payment->currency],
                    'callback_url' => $this->callbackUrl,
                    'custom_metadata' => array_filter([
                        'kora_payment_uuid' => $payment->uuid,
                        'kora_reservation_uuid' => $payment->reservation?->uuid,
                        'kora_wallet_topup_uuid' => $payment->walletTopUp?->uuid,
                    ]),
                ];

                if ($phone['number'] !== '') {
                    $transactionData['customer'] = [
                        'firstname' => $payload['customer_firstname'] ?? 'Passager',
                        'lastname' => $payload['customer_lastname'] ?? 'KORA',
                        'email' => $payload['customer_email'] ?? "passager+{$payment->uuid}@koraexpress.app",
                        'phone_number' => $phone,
                    ];
                }

                $transaction = $this->requireTransaction(Transaction::create($transactionData));
                $transactionId = (string) $transaction->id;
                $reference = (string) $transaction->reference;

                if ($isTopUp) {
                    $payment->update([
                        'provider_transaction_id' => $transactionId,
                        'provider_reference' => $reference,
                        'provider_payload' => array_replace($payment->provider_payload ?? [], ['initiation_phase' => 'fedapay_token_pending']),
                    ]);
                }
            }

            $instructions = [];
            if ($method === 'mobile_money' && $isTopUp) {
                $token = data_get($transaction->generateToken(), 'token');
                if (! is_string($token) || $token === '') {
                    throw new UnexpectedValueException('FedaPay payment token missing.');
                }

                $payment->update([
                    'provider_payload' => array_replace($payment->provider_payload ?? [], ['initiation_phase' => 'fedapay_push_in_flight']),
                ]);
                $transaction->sendNowWithToken($mode, $token, $phone);
            } elseif ($method === 'mobile_money') {
                $transaction->sendNow($mode, $phone);
            } elseif ($method === 'card' && $isTopUp) {
                $url = (string) data_get($transaction->generateToken(), 'url');
                if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                    throw new UnexpectedValueException('FedaPay checkout URL missing or invalid.');
                }
                $instructions['checkout_url'] = $url;
            }

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $reference,
                payload: $instructions,
                providerTransactionId: $transactionId,
            );
        } catch (Throwable $e) {
            $httpStatus = $e instanceof Base ? $e->getHttpStatus() : null;
            $rejected = $transactionId === null && in_array($httpStatus, [400, 401, 403, 422], true);
            $issue = in_array($httpStatus, [400, 422], true) ? 'invalid_payment_details' : 'provider_unavailable';
            Log::warning('FedaPay initiation failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
                'http_status' => $httpStatus,
                'phase' => $transactionId === null ? 'create' : 'checkout',
            ]);

            return new ProviderResponse(
                status: ! $isTopUp || $rejected ? PaymentStatus::Failed : PaymentStatus::Pending,
                providerReference: $reference,
                payload: ['initiation_issue' => $issue],
                message: 'Impossible de démarrer le paiement FedaPay.',
                providerTransactionId: $transactionId,
            );
        }
    }

    private function requireTransaction(mixed $transaction): Transaction
    {
        if (! $transaction instanceof Transaction) {
            throw new UnexpectedValueException('FedaPay transaction missing.');
        }

        return $transaction;
    }

    public function verify(Payment $payment): ProviderResponse
    {
        if ($payment->provider_reference === null && $payment->provider_transaction_id === null) {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                message: 'No provider reference available for polling.',
            );
        }

        try {
            $this->configure();

            /** @var mixed $transaction */
            $transaction = $payment->provider_transaction_id !== null
                ? Transaction::retrieve($payment->provider_transaction_id)
                : null;

            if ($transaction === null) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    message: 'Transaction not found on FedaPay.',
                );
            }

            $status = 'pending';
            $reference = $payment->provider_reference;
            try {
                $status = (string) $transaction->status;
            } catch (Throwable) {
            }
            try {
                $reference = (string) $transaction->reference;
            } catch (Throwable) {
            }
            /** @var array<string, mixed> $payloadArray */
            $payloadArray = $transaction->__toArray(true);

            if ($payment->reservation_id === null && ! isset($payloadArray['currency']) && isset($payloadArray['currency_id'])) {
                $payloadArray['currency'] = data_get(Currency::retrieve($payloadArray['currency_id']), 'iso');
            }

            return new ProviderResponse(
                status: $this->mapStatus($status),
                providerReference: $reference,
                payload: $payloadArray,
                message: "FedaPay status: {$status}",
                providerTransactionId: (string) $transaction->id,
            );
        } catch (Throwable $e) {
            Log::error('FedaPay verify failed', [
                'payment_uuid' => $payment->uuid,
                'exception' => $e::class,
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                payload: [],
                message: 'Failed to query FedaPay transaction.',
            );
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        $body = $request->getContent();
        $signature = $request->header('X-FedaPay-Signature');

        // Signature is always required.
        if ($signature === null) {
            Log::warning('FedaPay webhook missing signature header');

            return null;
        }

        if ($this->webhookSecret === null) {
            Log::warning('FedaPay webhook secret not configured');

            return null;
        }

        try {
            Webhook::constructEvent($body, $signature, $this->webhookSecret);
        } catch (Throwable $e) {
            Log::warning('FedaPay webhook signature mismatch', [
                'exception' => $e::class,
            ]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true) ?? [];

        return $data;
    }

    public function supports(string $method, string $provider): bool
    {
        if ($provider !== 'fedapay') {
            return false;
        }

        return in_array($method, ['mobile_money', 'card', 'wallet'], true);
    }

    public function name(): string
    {
        return 'fedapay';
    }
}
