<?php

namespace App\Modules\AppDistribution\Services;

use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\AppVersionStatus;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Collection;

/**
 * Données de l'écran « Versions de l'application » (MEZ-150) : releases de
 * l'application sélectionnée, liens publics à partager et distribution des
 * versions installées sur le parc.
 */
class AppReleaseAdminReadService
{
    public function __construct(private AppReleaseCatalog $catalog) {}

    /**
     * Onglets : une entrée par application distribuée.
     *
     * @return list<array{value: string, label: string, slug: string, releases_count: int}>
     */
    public function apps(): array
    {
        $counts = AppRelease::query()
            ->selectRaw('app, count(*) as aggregate')
            ->groupBy('app')
            ->pluck('aggregate', 'app');

        return array_map(fn (ReleasedApp $app): array => [
            'value' => $app->value,
            'label' => $app->label(),
            'slug' => $app->slug(),
            'releases_count' => (int) ($counts[$app->value] ?? 0),
        ], ReleasedApp::cases());
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(ReleasedApp $app): array
    {
        $latest = $this->catalog->latest($app);
        $mandatory = $this->catalog->latestMandatory($app);

        return [
            'app' => [
                'value' => $app->value,
                'label' => $app->label(),
                'slug' => $app->slug(),
                'audience' => $app->audience(),
            ],
            'links' => [
                'download_page' => route('downloads.apps.show', ['app' => $app->slug()]),
                'latest_apk' => route('downloads.apps.latest', ['app' => $app->slug()]),
            ],
            'latest' => $latest ? $this->serializeRelease($latest, $latest, $mandatory) : null,
            'minimum' => $mandatory ? [
                'uuid' => $mandatory->uuid,
                'version' => $mandatory->version,
                'build_number' => $mandatory->build_number,
            ] : null,
            'releases' => $this->releases($app, $latest, $mandatory),
            'distribution' => $this->distribution($app),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function releases(ReleasedApp $app, ?AppRelease $latest, ?AppRelease $mandatory): array
    {
        $releases = AppRelease::query()
            ->forApp($app)
            ->with(['creator:id,uuid,name', 'pushCampaign.creator:id,uuid,name', 'pushCampaign.retryDecisionBy:id,uuid,name'])
            ->get()
            ->sort(fn (AppRelease $left, AppRelease $right): int => $right->appVersion()->compare($left->appVersion()))
            ->map(fn (AppRelease $release): array => $this->serializeRelease($release, $latest, $mandatory))
            ->values()
            ->all();

        return array_values($releases);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRelease(AppRelease $release, ?AppRelease $latest, ?AppRelease $mandatory): array
    {
        return [
            'uuid' => $release->uuid,
            'version' => $release->version,
            'build_number' => $release->build_number,
            'full_version' => (string) $release->appVersion(),
            'release_notes' => $release->release_notes,
            'is_mandatory' => $release->is_mandatory,
            'is_latest' => $latest !== null && $latest->is($release),
            'is_minimum' => $mandatory !== null && $mandatory->is($release),
            'state' => $release->isArchived() ? 'archived' : 'published',
            'channel' => $release->channel->value,
            'platform' => $release->platform?->value,
            'environment' => $release->environment?->value,
            'store_url' => $release->store_url,
            'publicly_confirmed_at' => $release->publicly_confirmed_at?->toISOString(),
            'rollout_completed_at' => $release->rollout_completed_at?->toISOString(),
            'size_bytes' => $release->isStore() ? null : $release->artifact_size,
            'sha256' => $release->isStore() ? null : $release->sha256,
            'original_filename' => $release->original_filename,
            'download_url' => $release->isStore() ? null : $release->downloadUrl(),
            'published_at' => $release->published_at?->toISOString(),
            'archived_at' => $release->archived_at?->toISOString(),
            'created_at' => $release->created_at?->toISOString(),
            'author' => $release->relationLoaded('creator') && $release->creator ? [
                'uuid' => $release->creator->uuid,
                'name' => $release->creator->name,
            ] : null,
            'push_campaign' => $this->serializeCampaign($release),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeCampaign(AppRelease $release): ?array
    {
        $campaign = $release->relationLoaded('pushCampaign') ? $release->pushCampaign : null;

        if ($campaign === null) {
            return null;
        }

        return [
            'uuid' => $campaign->uuid,
            'topic' => $campaign->topic,
            'status' => $campaign->status->value,
            'title' => $campaign->title,
            'body' => $campaign->body,
            'attempts' => $campaign->attempts,
            'error' => $campaign->error,
            'fcm_message_id' => $campaign->fcm_message_id,
            'accepted_at' => $campaign->accepted_at?->toISOString(),
            'created_at' => $campaign->created_at?->toISOString(),
            'retry_decision_at' => $campaign->retry_decision_at?->toISOString(),
            'retry_decision_note' => $campaign->retry_decision_note,
            'author' => $campaign->relationLoaded('creator') && $campaign->creator ? [
                'uuid' => $campaign->creator->uuid,
                'name' => $campaign->creator->name,
            ] : null,
            'retry_decision_by' => $campaign->relationLoaded('retryDecisionBy') && $campaign->retryDecisionBy ? [
                'uuid' => $campaign->retryDecisionBy->uuid,
                'name' => $campaign->retryDecisionBy->name,
            ] : null,
        ];
    }

    /**
     * Répartition des versions installées sur le parc (terminaux non
     * perdus), regroupées par version lisible, de la plus récente à la plus
     * ancienne, avec le statut de mise à jour de chaque version.
     *
     * @return array{total: int, by_status: array<string, int>, versions: list<array{version: string|null, terminals: int, status: string, status_label: string}>}
     */
    private function distribution(ReleasedApp $app): array
    {
        /** @var Collection<int, TerminalP501> $terminals */
        $terminals = TerminalP501::query()
            ->where('status', '!=', TerminalP501Status::Lost->value)
            ->get(['id', 'type', 'app_version'])
            ->filter(fn (TerminalP501 $terminal): bool => $app->runsOn($terminal))
            ->values();

        $groups = $terminals
            ->groupBy(function (TerminalP501 $terminal): string {
                $version = AppVersion::parse($terminal->app_version);

                return $version !== null ? (string) $version : '';
            })
            ->map(function (Collection $group, string $version) use ($app): array {
                $check = $this->catalog->check($app, AppVersion::parse($version));

                return [
                    'version' => $version !== '' ? $version : null,
                    'terminals' => $group->count(),
                    'status' => $check->status->value,
                    'status_label' => $check->status->label(),
                ];
            })
            ->values()
            ->sort(function (array $left, array $right): int {
                $leftVersion = AppVersion::parse($left['version']);
                $rightVersion = AppVersion::parse($right['version']);

                return match (true) {
                    $leftVersion === null && $rightVersion === null => 0,
                    $leftVersion === null => 1,
                    $rightVersion === null => -1,
                    default => $rightVersion->compare($leftVersion),
                };
            })
            ->values();

        $byStatus = collect(AppVersionStatus::cases())
            ->mapWithKeys(fn (AppVersionStatus $status): array => [
                $status->value => (int) $groups->where('status', $status->value)->sum('terminals'),
            ])
            ->all();

        return [
            'total' => $terminals->count(),
            'by_status' => $byStatus,
            'versions' => array_values($groups->all()),
        ];
    }
}
