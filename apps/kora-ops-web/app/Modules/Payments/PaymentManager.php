<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
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
            'currency' => (string) config('app.currency', 'XOF'),
            'methods' => [
                [
                    'code' => 'mobile_money',
                    'label' => 'Mobile Money',
                    'enabled' => count($providers) > 0,
                    'providers' => $providers,
                ],
                [
                    'code' => 'wallet',
                    'label' => 'Mini Wallet',
                    'enabled' => false,
                    'availability' => 'out_of_mvp',
                    'providers' => [],
                ],
            ],
        ];
    }

    public function isAvailable(string $provider): bool
    {
        if (! $this->supports($provider) || $provider === 'fake') {
            return false;
        }

        return (bool) config("payments.providers.{$provider}.enabled", false)
            && $this->hasCredentials($provider);
    }

    /**
     * Validate the selected provider/network before creating a local payment.
     * In automated tests the configured fake bindings are intentionally allowed.
     */
    public function assertAvailable(string $method, string $provider, ?string $network = null): void
    {
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
            $environment = (string) config('payments.providers.mtn_momo.environment', 'sandbox');

            return (string) config(
                $environment === 'production'
                    ? 'payments.providers.mtn_momo.production_currency'
                    : 'payments.providers.mtn_momo.sandbox_currency',
                $environment === 'production' ? 'XOF' : 'EUR',
            );
        }

        return (string) config("payments.providers.{$provider}.currency", config('app.currency', 'XOF'));
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

    /**
     * @return list<string>
     */
    private function requiredCredentials(string $provider): array
    {
        return match ($provider) {
            'fedapay' => ['secret_key'],
            'mtn_momo' => ['subscription_key', 'user_id', 'api_key'],
            default => [],
        };
    }

    private function hasCredentials(string $provider): bool
    {
        foreach ($this->requiredCredentials($provider) as $credential) {
            if (blank(config("payments.providers.{$provider}.{$credential}"))) {
                return false;
            }
        }

        return true;
    }
}
