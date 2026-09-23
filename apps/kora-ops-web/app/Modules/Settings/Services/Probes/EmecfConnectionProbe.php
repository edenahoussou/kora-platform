<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Carbon;
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
                ->retry(min($this->settings->emecfRetry, 2), min($this->settings->emecfRetryDelay, 1000), null, false)
                ->get($this->statusEndpoint($baseUrl));

            if ($response->successful()) {
                $payload = (array) $response->json();

                if (($payload['status'] ?? null) === false) {
                    return ConnectionProbeResult::failure(
                        provider: $this->provider(),
                        environment: $environment,
                        durationMs: $this->duration($startedAt),
                        message: 'e-MECeF signale un statut inactif pour ce jeton.',
                    );
                }

                try {
                    $this->persistAccountInfo($payload);
                } catch (Throwable) {
                    // Account persistence must never break a connectivity check.
                }

                return ConnectionProbeResult::success(
                    provider: $this->provider(),
                    environment: $environment,
                    durationMs: $this->duration($startedAt),
                    message: 'e-MECeF connecté — '.$this->describe($payload),
                );
            }

            $unavailable = $response->serverError();

            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: $unavailable
                    ? 'e-MECeF est indisponible pour le moment.'
                    : sprintf('e-MECeF a refusé la vérification de connexion (HTTP %d).', $response->status()),
                status: $unavailable ? 'unavailable' : 'failed',
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

    /**
     * The settings store the information API base ({...}/api/info). Older rows
     * may still hold the API root ({...}/api) — normalize before hitting /status.
     */
    private function statusEndpoint(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (! str_ends_with($baseUrl, '/info')) {
            $baseUrl .= '/info';
        }

        return $baseUrl.'/status';
    }

    /** @param array<string, mixed> $payload */
    private function describe(array $payload): string
    {
        $emcf = (array) ($payload['emcfList'][0] ?? []);

        $details = array_filter([
            'IFU '.($payload['ifu'] ?? 'inconnu'),
            'NIM '.($payload['nim'] ?? 'inconnu'),
            filled($emcf['shopName'] ?? null) ? 'Structure : '.$emcf['shopName'] : null,
            filled($emcf['status'] ?? null) ? 'e-MCF '.$emcf['status'] : null,
            implode(', ', array_filter([
                $emcf['address1'] ?? null,
                $emcf['address2'] ?? null,
                $emcf['address3'] ?? null,
            ])) ?: null,
            implode(' / ', array_filter([
                $emcf['contact1'] ?? null,
                $emcf['contact2'] ?? null,
                $emcf['contact3'] ?? null,
            ])) ?: null,
            isset($payload['tokenValid']) ? 'jeton valide jusqu’au '.$this->formatDate((string) $payload['tokenValid']) : null,
            isset($payload['serverDateTime']) ? 'serveur '.$this->formatDate((string) $payload['serverDateTime']) : null,
            isset($payload['version']) ? 'API v'.$payload['version'] : null,
        ]);

        return implode(' · ', $details);
    }

    /**
     * Stores the taxpayer account details returned by the DGI so invoice views
     * can render them. Runs only on the first successful sync — values edited
     * afterwards through the settings page are never overwritten.
     *
     * @param  array<string, mixed>  $payload
     */
    private function persistAccountInfo(array $payload): void
    {
        if (filled($this->settings->emecfNim)) {
            return;
        }

        $emcf = (array) ($payload['emcfList'][0] ?? []);

        $this->settings->emecfNim = isset($emcf['nim']) ? (string) $emcf['nim'] : (isset($payload['nim']) ? (string) $payload['nim'] : null);
        $this->settings->emecfShopName = $emcf['shopName'] ?? null;
        $this->settings->emecfAccountStatus = $emcf['status'] ?? null;
        $this->settings->emecfAddress1 = $emcf['address1'] ?? null;
        $this->settings->emecfAddress2 = $emcf['address2'] ?? null;
        $this->settings->emecfAddress3 = $emcf['address3'] ?? null;
        $this->settings->emecfContact1 = $emcf['contact1'] ?? null;
        $this->settings->emecfContact2 = $emcf['contact2'] ?? null;
        $this->settings->emecfContact3 = $emcf['contact3'] ?? null;
        $this->settings->emecfApiVersion = isset($payload['version']) ? (string) $payload['version'] : null;
        $this->settings->emecfTokenValidUntil = isset($payload['tokenValid']) ? (string) $payload['tokenValid'] : null;

        if (($this->settings->emecfDefaultIfu === '0000000000000' || blank($this->settings->emecfDefaultIfu)) && filled($payload['ifu'] ?? null)) {
            $this->settings->emecfDefaultIfu = (string) $payload['ifu'];
        }

        $this->settings->save();
    }

    private function formatDate(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y à H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
