<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

final class EmecfConnectionProbe implements IntegrationConnectionProbe
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function provider(): string
    {
        return 'emecf';
    }

    public function test(): ConnectionProbeResult
    {
        $startedAt = microtime(true);
        $environment = $this->settings->emecfTestMode ? 'test' : 'production';

        if (blank($this->settings->emecfToken)) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'Le jeton e-MECeF n’est pas configuré.',
            );
        }

        $baseUrl = $this->settings->emecfTestMode
            ? $this->settings->emecfTestInfoUrl
            : $this->settings->emecfProductionInfoUrl;

        try {
            $response = Http::acceptJson()
                ->withToken((string) $this->settings->emecfToken)
                ->timeout(min($this->settings->emecfHttpTimeout, 30))
                ->connectTimeout(min($this->settings->emecfConnectTimeout, 10))
                ->retry(min($this->settings->emecfRetry, 2), min($this->settings->emecfRetryDelay, 1000))
                ->get(rtrim($baseUrl, '/').'/info');

            if ($response->successful()) {
                return ConnectionProbeResult::success(
                    provider: $this->provider(),
                    environment: $environment,
                    durationMs: $this->duration($startedAt),
                    message: 'e-MECeF répond correctement.',
                );
            }

            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'e-MECeF a refusé la vérification de connexion.',
            );
        } catch (Throwable) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'e-MECeF est indisponible pour le moment.',
                status: 'unavailable',
            );
        }
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
