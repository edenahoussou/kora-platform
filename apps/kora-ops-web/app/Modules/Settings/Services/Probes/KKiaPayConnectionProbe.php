<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

final class KKiaPayConnectionProbe implements IntegrationConnectionProbe
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function provider(): string
    {
        return 'kkiapay';
    }

    public function test(): ConnectionProbeResult
    {
        $startedAt = microtime(true);
        $environment = $this->settings->kkiapayEnvironment;

        if (blank($this->settings->kkiapayPublicKey) || blank($this->settings->kkiapayPrivateKey) || blank($this->settings->kkiapaySecretKey)) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'Les clés API KKiaPay ne sont pas configurées.',
            );
        }

        $baseUrl = $environment === 'production'
            ? 'https://api.kkiapay.me'
            : 'https://api-sandbox.kkiapay.me';

        try {
            // A dummy transactionId probes authentication: a non-401/403
            // answer means the API keys were accepted.
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-KEY' => (string) $this->settings->kkiapayPublicKey,
                'X-PRIVATE-KEY' => (string) $this->settings->kkiapayPrivateKey,
                'X-SECRET-KEY' => (string) $this->settings->kkiapaySecretKey,
            ])->timeout(10)->post("{$baseUrl}/api/v1/transactions/status", [
                'transactionId' => 'kora-probe',
            ]);

            if (in_array($response->status(), [401, 403], true)) {
                return ConnectionProbeResult::failure(
                    provider: $this->provider(),
                    environment: $environment,
                    durationMs: $this->duration($startedAt),
                    message: 'KKiaPay a refusé les clés API configurées.',
                );
            }

            return ConnectionProbeResult::success(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'KKiaPay répond correctement.',
            );
        } catch (Throwable) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'KKiaPay est injoignable.',
            );
        }
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
