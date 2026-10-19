<?php

namespace App\Modules\Settings\Services\Probes;

use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\Sms\ConexteoSmsException;
use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;

final class ConexteoConnectionProbe implements IntegrationConnectionProbe
{
    public function __construct(private readonly ConexteoSmsClient $client) {}

    public function provider(): string
    {
        return 'conexteo';
    }

    public function test(): ConnectionProbeResult
    {
        $startedAt = microtime(true);
        $environment = 'production';

        if (! $this->client->isConfigured()) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'Les identifiants Conexteo sont incomplets.',
            );
        }

        try {
            $preview = $this->client->preview('+22990000000', 'Test de connexion KORA BUS');

            return ConnectionProbeResult::success(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: 'Conexteo répond correctement — '.$this->describeCredits($preview).'.',
            );
        } catch (ConexteoSmsException $exception) {
            return ConnectionProbeResult::failure(
                provider: $this->provider(),
                environment: $environment,
                durationMs: $this->duration($startedAt),
                message: $this->safeMessage($exception),
                status: $exception->shouldRetry() ? 'unavailable' : 'failed',
            );
        }
    }

    /** @param array{credits_used: int|null, credits_remaining: int|null} $preview */
    private function describeCredits(array $preview): string
    {
        return implode(', ', array_filter([
            $preview['credits_used'] !== null ? $preview['credits_used'].' crédit(s) requis' : null,
            $preview['credits_remaining'] !== null ? $preview['credits_remaining'].' crédit(s) restant(s)' : null,
        ])) ?: 'aucun crédit consommé';
    }

    private function safeMessage(ConexteoSmsException $exception): string
    {
        return match ($exception->status()) {
            401, 403 => 'Conexteo a refusé les identifiants (HTTP '.$exception->status().').',
            null => 'Conexteo est indisponible pour le moment.',
            default => $exception->status() >= 500
                ? 'Conexteo est indisponible pour le moment.'
                : 'Conexteo a refusé la vérification de connexion (HTTP '.$exception->status().').',
        };
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
