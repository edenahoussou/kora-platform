<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MtnMomoConnectionProbe implements IntegrationConnectionProbe
{
    private const SANDBOX_URL = 'https://sandbox.momodeveloper.mtn.com';

    private const PRODUCTION_URL = 'https://momodeveloper.mtn.com';

    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function provider(): string
    {
        return 'mtn_momo';
    }

    public function test(): ConnectionProbeResult
    {
        $startedAt = microtime(true);
        $environment = $this->settings->mtnMomoEnvironment;

        if (blank($this->settings->mtnMomoSubscriptionKey)
            || blank($this->settings->mtnMomoUserId)
            || blank($this->settings->mtnMomoApiKey)) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'Les identifiants MTN MoMo sont incomplets.',
            );
        }

        try {
            $baseUrl = $environment === 'production'
                ? self::PRODUCTION_URL
                : self::SANDBOX_URL;

            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode(
                        $this->settings->mtnMomoUserId.':'.$this->settings->mtnMomoApiKey,
                    ),
                    'Ocp-Apim-Subscription-Key' => $this->settings->mtnMomoSubscriptionKey,
                ])
                ->timeout(15)
                ->connectTimeout(5)
                ->retry(2, 100)
                ->post($baseUrl.'/collection/token/');

            if ($response->successful() && filled($response->json('access_token'))) {
                return ConnectionProbeResult::success(
                    provider: $this->provider(),
                    environment: $environment,
                    durationMs: $this->duration($startedAt),
                    message: 'MTN MoMo répond correctement.',
                );
            }

            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'MTN MoMo a refusé la vérification de connexion.',
            );
        } catch (Throwable) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'MTN MoMo est indisponible pour le moment.',
                status: 'unavailable',
            );
        }
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
