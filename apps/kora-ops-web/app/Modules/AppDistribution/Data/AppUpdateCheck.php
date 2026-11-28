<?php

namespace App\Modules\AppDistribution\Data;

use App\Modules\AppDistribution\Models\AppRelease;

/**
 * Résultat du calcul « cette installation doit-elle se mettre à jour ? »
 * pour une application et une version installée (#342).
 *
 * Règle produit : toute version installée antérieure à la DERNIÈRE release
 * obligatoire publiée est forcée de se mettre à jour (`mandatory = true`),
 * même si des releases non obligatoires plus récentes existent entre les
 * deux ; la cible proposée est toujours la dernière release publiée.
 */
final class AppUpdateCheck
{
    public function __construct(
        public readonly ReleasedApp $app,
        public readonly ?AppVersion $installed,
        public readonly ?AppRelease $latest,
        public readonly ?AppRelease $mandatoryRelease,
        public readonly AppVersionStatus $status,
        public readonly ?ReleasePlatform $platform = null,
        public readonly ?ReleaseEnvironment $environment = null,
        public readonly ReleaseChannel $channel = ReleaseChannel::OpsApk,
    ) {}

    public function updateAvailable(): bool
    {
        return in_array($this->status, [AppVersionStatus::UpdateAvailable, AppVersionStatus::UpdateRequired], true);
    }

    public function mandatory(): bool
    {
        return $this->status === AppVersionStatus::UpdateRequired;
    }

    /**
     * Bloc `update` renvoyé par la vérification au lancement et réutilisable
     * dans les réponses internes. `null` tant qu'aucune release publique
     * n'existe pour l'application/canal/plateforme/environnement.
     *
     * @return array<string, mixed>|null
     */
    public function toPayload(): ?array
    {
        if ($this->latest === null) {
            return null;
        }

        $latestVersion = $this->latest->appVersion();
        $payload = [
            'app' => $this->app->value,
            'channel' => $this->channel->value,
            'platform' => $this->platform?->value,
            'environment' => $this->environment?->value,
            'status' => $this->status->value,
            'installed_version' => $this->installed?->semver(),
            'installed_build' => $this->installed?->build,
            'latest_version' => $this->latest->version,
            'latest_build' => $this->latest->build_number,
            'latest_full_version' => (string) $latestVersion,
            'update_available' => $this->updateAvailable(),
            'mandatory' => $this->mandatory(),
            'minimum_version' => $this->mandatoryRelease?->version,
            'minimum_build' => $this->mandatoryRelease?->build_number,
            'release_uuid' => $this->latest->uuid,
            'release_notes' => $this->latest->release_notes,
            'published_at' => $this->latest->published_at?->toISOString(),
        ];

        if ($this->channel === ReleaseChannel::Store) {
            $payload['store_url'] = $this->latest->store_url;

            return $payload;
        }

        $payload['download_url'] = $this->latest->downloadUrl();
        $payload['download_page_url'] = route('downloads.apps.show', ['app' => $this->app->slug()]);
        $payload['sha256'] = $this->latest->sha256;
        $payload['size_bytes'] = $this->latest->artifact_size;

        return $payload;
    }
}
