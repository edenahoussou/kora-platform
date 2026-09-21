<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Resolves the correct payment provider for a given method and provider key.
 *
 * Providers are registered in config/payments.php and lazily instantiated.
 */
class PaymentManager
{
    /** @var array<string, PaymentProvider> */
    private array $resolved = [];

    /**
     * @return array<string, class-string<PaymentProvider>>
     */
    private function providers(): array
    {
        return [
            'fedapay' => FedaPayAdapter::class,
            'mtn_momo' => MtnMomoAdapter::class,
            'fake' => FakePaymentProvider::class,
        ];
    }

    public function resolve(string $provider): PaymentProvider
    {
        if (isset($this->resolved[$provider])) {
            return $this->resolved[$provider];
        }

        $providers = $this->providers();

        if (! isset($providers[$provider])) {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not supported.");
        }

        return $this->resolved[$provider] = app($providers[$provider]);
    }

    /**
     * Resolve a provider by inspecting the webhook request path or payload.
     */
    public function resolveFromWebhook(string $providerKey): PaymentProvider
    {
        return $this->resolve($providerKey);
    }

    /**
     * Check if a provider is supported.
     */
    public function supports(string $provider): bool
    {
        return array_key_exists($provider, $this->providers());
    }

    /**
     * Return the public payment catalog consumed by mobile clients.
     *
     * @return array{country: string, currency: string, methods: array<int, array<string, mixed>>}
     */
    public function catalog(): array
    {
        $providers = [];

        foreach (['fedapay', 'mtn_momo'] as $provider) {
            if (! $this->isAvailable($provider)) {
                continue;
            }

            $networks = $provider === 'fedapay'
                ? MobileMoneyNetwork::values()
                : [MobileMoneyNetwork::MtnMomo->value];

            $providers[] = [
                'code' => $provider,
                'label' => $provider === 'fedapay' ? 'FedaPay' : 'MTN MoMo',
                'enabled' => true,
                'networks' => $networks,
                'requires' => ['phone', 'mobile_money_network'],
                'interaction' => $provider === 'fedapay' ? 'push_or_redirect' : 'push',
                'polling_interval_seconds' => 10,
                'verification' => 'server_verify',
            ];
        }

        return [
            'country' => 'BJ',
            'currency' => app(GeneralSettings::class)->currency,
            'methods' => [
                [
                    'code' => 'mobile_money',
                    'label' => 'Mobile Money',
                    'enabled' => count($providers) > 0,
                    'providers' => $providers,
                ],
                [
                    'code' => 'wallet',
                    'label' => 'Wallet KORA BUS',
                    'enabled' => false,
                    'availability' => 'disabled_until_wallet_ledger',
                    'providers' => [],
                    'requires' => ['wallet_amount'],
                ],
            ],
        ];
    }

    public function isAvailable(string $provider): bool
    {
        if (! $this->supports($provider) || $provider === 'fake') {
            return false;
        }

        $settings = app(IntegrationsSettings::class);

        return (bool) ($provider === 'fedapay' ? $settings->fedapayEnabled : $settings->mtnMomoEnabled)
            && $this->hasCredentials($provider);
    }

    /**
     * Validate the selected provider/network before creating a local payment.
     * In automated tests the configured fake bindings are intentionally allowed.
     */
    public function assertAvailable(string $method, string $provider, ?string $network = null): void
    {
        if ($method === 'wallet') {
            throw ValidationException::withMessages([
                'method' => 'Le Wallet est prévu dans le contrat mobile mais n’est pas encore activé.',
            ]);
        }

        if ($method !== 'mobile_money') {
            throw ValidationException::withMessages(['method' => 'Le mode de paiement n’est pas disponible dans l’application mobile.']);
        }

        if (! $this->supports($provider) || ($provider !== 'fake' && ! $this->isAvailable($provider) && ! app()->environment('testing'))) {
            throw ValidationException::withMessages(['provider' => 'Ce fournisseur de paiement n’est pas configuré ou actif.']);
        }

        $network = $network ?: ($provider === 'mtn_momo' ? MobileMoneyNetwork::MtnMomo->value : null);
        if ($network === null || ! in_array($network, $this->networksFor($provider), true)) {
            throw ValidationException::withMessages(['mobile_money_network' => 'Sélectionnez un réseau Mobile Money compatible avec ce fournisseur.']);
        }
    }

    /**
     * @return array<int, string>
     */
    public function networksFor(string $provider): array
    {
        return $provider === 'fedapay'
            ? MobileMoneyNetwork::values()
            : ($provider === 'mtn_momo' ? [MobileMoneyNetwork::MtnMomo->value] : []);
    }

    public function providerCurrency(string $provider): string
    {
        if ($provider === 'mtn_momo') {
            $settings = app(IntegrationsSettings::class);
            $environment = $settings->mtnMomoEnvironment;

            return $environment === 'production'
                ? $settings->mtnMomoProductionCurrency
                : $settings->mtnMomoSandboxCurrency;
        }

        return $provider === 'fedapay'
            ? app(IntegrationsSettings::class)->fedapayCurrency
            : app(GeneralSettings::class)->currency;
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function providerAmount(string $provider, int $amount, string $currency): array
    {
        $providerCurrency = $this->providerCurrency($provider);

        if ($providerCurrency === $currency || $this->resolve($provider) instanceof FakePaymentProvider) {
            return ['amount' => $amount, 'currency' => $providerCurrency];
        }

        if ($provider === 'mtn_momo') {
            // EUR is a sandbox protocol requirement, not a business FX rate.
            // Keep the numeric test amount unchanged and never convert customer fares.
            return ['amount' => $amount, 'currency' => $providerCurrency];
        }

        throw ValidationException::withMessages([
            'provider' => "La devise {$providerCurrency} n’est pas compatible avec la devise de la réservation {$currency}.",
        ]);
    }

    private function hasCredentials(string $provider): bool
    {
        $settings = app(IntegrationsSettings::class);

        return match ($provider) {
            'fedapay' => filled($settings->fedapaySecretKey),
            'mtn_momo' => filled($settings->mtnMomoSubscriptionKey)
                && filled($settings->mtnMomoUserId)
                && filled($settings->mtnMomoApiKey),
            default => true,
        };
    }
}
