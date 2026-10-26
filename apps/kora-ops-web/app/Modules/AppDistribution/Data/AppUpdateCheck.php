<?php

namespace App\Modules\AppDistribution\Data;

use App\Modules\AppDistribution\Models\AppRelease;

/**
 * Résultat du calcul « cette installation doit-elle se mettre à jour ? »
 * pour une application et une version installée (MEZ-150).
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
     * Bloc `update` ajouté aux réponses OPS Mobile (handshake, sync,
     * activity, session) et renvoyé par la vérification au lancement.
     * `null` tant qu'aucune release n'est publiée pour l'application : le
     * client n'a alors rien à proposer.
     *
     * @return array<string, mixed>|null
     */
    public function toPayload(): ?array
    {
        if ($this->latest === null) {
            return null;
        }

        $latestVersion = $this->latest->appVersion();

        return [
            'app' => $this->app->value,
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
            'download_url' => $this->latest->downloadUrl(),
            'download_page_url' => route('downloads.apps.show', ['app' => $this->app->slug()]),
            'sha256' => $this->latest->sha256,
            'size_bytes' => $this->latest->artifact_size,
            'published_at' => $this->latest->published_at?->toISOString(),
        ];
    }
}
