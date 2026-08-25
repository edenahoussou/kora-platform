<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
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
}
