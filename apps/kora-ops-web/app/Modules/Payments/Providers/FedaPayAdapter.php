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
        $country = (string) ($payload['phone_country'] ?? $this->defaultPhoneCountry);

        return ['number' => $phone, 'country' => $country];
    }

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        try {
            $this->configure();

            $phone = $this->extractPhone($payload);

            $transactionData = [
                'description' => "KORA BUS reservation {$payment->reservation->uuid}",
                'amount' => $payment->provider_amount ?? $payment->amount,
                'currency' => ['iso' => $payment->provider_currency ?? $payment->currency],
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

            if (($payload['method'] ?? null) === 'mobile_money') {
                $network = (string) ($payload['mobile_money_network'] ?? '');
                $mode = $this->mobileMoneyModes[$network] ?? null;

                if ($mode === null || $phone['number'] === '') {
                    throw new \InvalidArgumentException('Le réseau et le numéro sont nécessaires pour initier FedaPay.');
                }

                $transaction = $transaction->sendNow($mode, [
                    'number' => $phone['number'],
                    'country' => $phone['country'],
                ]);
            }

            /** @var array<string, mixed> $transactionArray */
            $transactionArray = is_object($transaction) && method_exists($transaction, 'toArray') ? $transaction->toArray() : [];
            $reference = '';
            $transactionId = null;
            if (is_object($transaction) && property_exists($transaction, 'reference')) {
                $reference = (string) $transaction->reference;
            }
            if (is_object($transaction) && property_exists($transaction, 'id')) {
                $transactionId = (string) $transaction->id;
            }

            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $reference,
                payload: array_merge($transactionArray, array_filter(['id' => $transactionId])),
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

    public function verify(Payment $payment): ProviderResponse
    {
        if ($payment->provider_reference === null) {
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

                $transaction = $transactionList[0];
            }
            $status = is_object($transaction) && property_exists($transaction, 'status') ? (string) $transaction->status : 'pending';
            $reference = is_object($transaction) && property_exists($transaction, 'reference') ? (string) $transaction->reference : $payment->provider_reference;
            /** @var array<string, mixed> $payloadArray */
            $payloadArray = is_object($transaction) && method_exists($transaction, 'toArray') ? $transaction->toArray() : [];

            return new ProviderResponse(
                status: $this->mapStatus($status),
                providerReference: $reference,
                payload: $payloadArray,
                message: "FedaPay status: {$status}",
            );
        } catch (Throwable $e) {
            Log::error('FedaPay verify failed', [
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
