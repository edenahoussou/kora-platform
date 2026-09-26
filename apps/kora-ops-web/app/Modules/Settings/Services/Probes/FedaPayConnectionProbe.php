<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Settings\IntegrationsSettings;
use FedaPay\Account;
use FedaPay\FedaPay;
use Throwable;

final class FedaPayConnectionProbe implements IntegrationConnectionProbe
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function provider(): string
    {
        return 'fedapay';
    }

    public function test(): ConnectionProbeResult
    {
        $startedAt = microtime(true);
        $environment = $this->settings->fedapayEnvironment;

        if (blank($this->settings->fedapaySecretKey)) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'La clé secrète FedaPay n’est pas configurée.',
            );
        }

        try {
            FedaPay::setApiKey((string) $this->settings->fedapaySecretKey);
            FedaPay::setEnvironment($environment);
            Account::light();

            return ConnectionProbeResult::success(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'FedaPay répond correctement.',
            );
        } catch (Throwable) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'FedaPay a refusé la vérification de connexion.',
            );
        }
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
