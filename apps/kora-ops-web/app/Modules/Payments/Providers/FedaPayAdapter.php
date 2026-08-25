<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            'approved' => PaymentStatus::Confirmed,
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
        $country = (string) ($payload['phone_country'] ?? 'ci');

        return ['number' => $phone, 'country' => $country];
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        try {
            $this->configure();

            $phone = $this->extractPhone($payload);

            $transactionData = [
                'description' => "KORA BUS reservation {$payment->reservation->uuid}",
                'amount' => $payment->amount,
                'currency' => ['iso' => $payment->currency],
                'callback_url' => $this->callbackUrl,
                'custom_metadata' => [
                    'kora_payment_uuid' => $payment->uuid,
                    'kora_reservation_uuid' => $payment->reservation->uuid,
                    'kora_idempotency_key' => $payment->idempotency_key,
                ],
            ];

            if ($phone['number'] !== '') {
                $transactionData['customer'] = [
                    'firstname' => $payload['customer_firstname'] ?? 'Passager',
                    'lastname' => $payload['customer_lastname'] ?? 'KORA',
                    'email' => $payload['customer_email'] ?? "passager+{$payment->uuid}@koraexpress.app",
                    'phone_number' => [
                        'number' => $phone['number'],
                        'country' => $phone['country'],
                    ],
                ];
            }

            /** @var mixed $transaction */
            $transaction = Transaction::create($transactionData);

            /** @var array<string, mixed> $transactionArray */
            $transactionArray = is_object($transaction) && method_exists($transaction, 'toArray') ? $transaction->toArray() : [];
            $reference = '';
            if (is_object($transaction) && property_exists($transaction, 'reference')) {
                $reference = (string) $transaction->reference;
            }

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $reference,
                payload: $transactionArray,
                message: 'Transaction created on FedaPay.',
            );
        } catch (Throwable $e) {
            Log::error('FedaPay initiate failed', [
                'payment_uuid' => $payment->uuid,
                'error' => $e->getMessage(),
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Failed,
                payload: ['error' => $e->getMessage()],
                message: 'Failed to initiate FedaPay transaction.',
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
            $this->configure();

            /** @var mixed $transactions */
            $transactions = Transaction::all(['reference' => $payment->provider_reference]);
            /** @var array<int, mixed> $transactionList */
            $transactionList = is_iterable($transactions) ? iterator_to_array($transactions) : [];

            if (count($transactionList) === 0) {
                return new ProviderResponse(
                    status: PaymentStatus::Pending,
                    message: 'Transaction not found on FedaPay.',
                );
            }

            /** @var mixed $transaction */
            $transaction = $transactionList[0];
            $status = is_object($transaction) && property_exists($transaction, 'status') ? (string) $transaction->status : 'pending';
            $reference = is_object($transaction) && property_exists($transaction, 'reference') ? (string) $transaction->reference : $payment->provider_reference;
            /** @var array<string, mixed> $payloadArray */
            $payloadArray = is_object($transaction) && method_exists($transaction, 'toArray') ? $transaction->toArray() : [];

            return new ProviderResponse(
                status: $this->mapStatus($status),
                providerReference: $reference,
                payload: $payloadArray,
                message: "FedaPay status: {$transaction->status}",
            );
        } catch (Throwable $e) {
            Log::error('FedaPay confirm failed', [
                'payment_uuid' => $payment->uuid,
                'error' => $e->getMessage(),
            ]);

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                payload: ['error' => $e->getMessage()],
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

        $computed = hash_hmac('sha256', $body, $this->webhookSecret);

        if (! hash_equals($computed, $signature)) {
            Log::warning('FedaPay webhook signature mismatch');

            return null;
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            Log::warning('FedaPay webhook invalid JSON');

            return null;
        }

        return $payload;
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
