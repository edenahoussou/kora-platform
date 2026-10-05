<?php

namespace App\Modules\Finance\Services;

use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Models\Payment;

final class RevenueClassifier
{
    /** @var array<string, string> */
    private const INTEGRATION_LABELS = [
        'fedapay' => 'FedaPay',
        'mtn_momo' => 'MTN MoMo',
    ];

    /**
     * @return array{key: string, label: string, qualified: bool, reason: string|null}
     */
    public function channel(Payment $payment): array
    {
        return $this->channelFromValues(
            $payment->reservation?->channel?->value,
            $this->rawValue($payment, 'provider'),
            $this->rawValue($payment, 'provider_reference'),
            $this->rawValue($payment, 'provider_transaction_id'),
        );
    }

    /**
     * @return array{key: string, label: string, qualified: bool, reason: string|null}
     */
    public function channelFromValues(
        ?string $channel,
        ?string $provider,
        ?string $providerReference = null,
        ?string $providerTransactionId = null,
    ): array {
        $hasIntegration = $this->isRecognizedIntegration($provider)
            && ($providerReference !== null || $providerTransactionId !== null);

        return match ($channel) {
            ReservationChannel::Counter->value => [
                'key' => 'counter',
                'label' => 'Vente au guichet',
                'qualified' => true,
                'reason' => null,
            ],
            ReservationChannel::Mobile->value => [
                'key' => 'mobile',
                'label' => 'Vente via l’application passager',
                'qualified' => true,
                'reason' => null,
            ],
            ReservationChannel::CompanyPortal->value => [
                'key' => 'company_portal',
                'label' => 'Vente portail entreprise',
                'qualified' => true,
                'reason' => null,
            ],
            ReservationChannel::Online->value => $hasIntegration
                ? [
                    'key' => 'mobile',
                    'label' => 'Vente via l’application passager',
                    'qualified' => true,
                    'reason' => null,
                ]
                : $this->unclassifiedChannel('online'),
            ReservationChannel::Ops->value => $this->unclassifiedChannel('ops'),
            default => $this->unclassifiedChannel($channel ?? 'unknown'),
        };
    }

    /**
     * @return array{key: string, label: string, method: string, context: string, integration: string|null, network: string|null, account: string|null, qualified: bool}
     */
    public function source(Payment $payment): array
    {
        $channel = $this->channel($payment);
        $network = $this->rawValue($payment, 'mobile_money_network');

        return $this->sourceFromValues(
            $payment->method?->value,
            $this->rawValue($payment, 'provider'),
            $network,
            $channel['key'],
            $this->rawValue($payment, 'provider_reference'),
            $this->rawValue($payment, 'provider_transaction_id'),
            $payment->agencyPaymentAccount?->label,
        );
    }

    /**
     * @return array{key: string, label: string, method: string, context: string, integration: string|null, network: string|null, account: string|null, qualified: bool}
     */
    public function sourceFromValues(
        ?string $method,
        ?string $provider,
        ?string $network,
        string $context,
        ?string $providerReference = null,
        ?string $providerTransactionId = null,
        ?string $account = null,
    ): array {
        $method ??= 'unknown';
        $channelLabel = $context === 'counter'
            ? 'Guichet'
            : match ($context) {
                'mobile' => 'Application passager',
                'company_portal' => 'Espace entreprise',
                default => 'Canal à qualifier',
            };
        $networkLabel = $this->networkLabel($network);
        $integrationLabel = $this->integrationLabel($provider);
        $isIntegrated = $context === 'mobile'
            && $integrationLabel !== null
            && ($providerReference !== null || $providerTransactionId !== null);

        if ($method === PaymentMethod::Cash->value) {
            return [
                'key' => $context.':cash',
                'label' => 'Espèces · '.$channelLabel,
                'method' => $method,
                'context' => $context,
                'integration' => null,
                'network' => null,
                'account' => $account,
                'qualified' => $context !== 'unclassified',
            ];
        }

        if ($method === PaymentMethod::MobileMoney->value && $isIntegrated) {
            $suffix = $networkLabel === null ? '' : ' · '.$networkLabel;

            return [
                'key' => $context.':mobile_money:'.$provider.':'.($network ?? 'unknown'),
                'label' => $integrationLabel.$suffix,
                'method' => $method,
                'context' => $context,
                'integration' => $integrationLabel,
                'network' => $networkLabel,
                'account' => $account,
                'qualified' => true,
            ];
        }

        if ($method === PaymentMethod::MobileMoney->value && $context === 'counter') {
            $suffix = $networkLabel === null ? '' : ' · '.$networkLabel;

            return [
                'key' => $context.':mobile_money:'.($network ?? 'unknown'),
                'label' => 'Mobile Money'.$suffix.' · Guichet',
                'method' => $method,
                'context' => $context,
                'integration' => null,
                'network' => $networkLabel,
                'account' => $account,
                'qualified' => $networkLabel !== null,
            ];
        }

        if (in_array($method, [PaymentMethod::Wallet->value, PaymentMethod::CompanyCredit->value], true)) {
            $label = $method === PaymentMethod::Wallet->value ? 'Wallet' : 'Crédit entreprise';

            return [
                'key' => $context.':'.$method,
                'label' => $label.' · '.$channelLabel,
                'method' => $method,
                'context' => $context,
                'integration' => $isIntegrated ? $integrationLabel : null,
                'network' => $networkLabel,
                'account' => $account,
                'qualified' => $context !== 'unclassified',
            ];
        }

        return [
            'key' => $context.':unknown',
            'label' => 'Source à qualifier',
            'method' => $method,
            'context' => $context,
            'integration' => $integrationLabel,
            'network' => $networkLabel,
            'account' => $account,
            'qualified' => false,
        ];
    }

    public function isRecognizedIntegration(?string $provider): bool
    {
        return $provider !== null && array_key_exists($provider, self::INTEGRATION_LABELS);
    }

    private function integrationLabel(?string $provider): ?string
    {
        return $provider === null ? null : (self::INTEGRATION_LABELS[$provider] ?? null);
    }

    private function networkLabel(?string $network): ?string
    {
        if ($network === null) {
            return null;
        }

        try {
            return MobileMoneyNetwork::from($network)->label();
        } catch (\ValueError) {
            return $network;
        }
    }

    /**
     * @return array{key: string, label: string, qualified: bool, reason: string}
     */
    private function unclassifiedChannel(string $source): array
    {
        return [
            'key' => 'unclassified',
            'label' => 'Canal historique à qualifier',
            'qualified' => false,
            'reason' => $source,
        ];
    }

    private function rawValue(Payment $payment, string $attribute): ?string
    {
        $value = $payment->getRawOriginal($attribute);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
