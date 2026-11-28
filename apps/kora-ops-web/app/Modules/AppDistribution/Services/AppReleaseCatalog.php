<?php

namespace App\Modules\AppDistribution\Services;

use App\Modules\AppDistribution\Data\AppUpdateCheck;
use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\AppVersionStatus;
use App\Modules\AppDistribution\Data\ReleaseChannel;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Source unique du calcul « dernière version / version obligatoire » d'une
 * application distribuée par KORA OPS Web (#342).
 *
 * Le tri se fait en PHP selon la précédence semver + build, qu'un ORDER BY
 * SQL ne sait pas exprimer. Les releases sont mises en cache par paires
 * canal/plateforme/environnement et invalidées à chaque écriture.
 */
class AppReleaseCatalog
{
    private const CACHE_TTL_SECONDS = 600;

    /**
     * Releases APK publiées et non archivées, de la plus récente à la plus
     * ancienne (canal Convoyeur existant).
     *
     * @return Collection<int, AppRelease>
     */
    public function published(ReleasedApp $app): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::apkCacheKey($app),
            self::CACHE_TTL_SECONDS,
            fn (): array => AppRelease::query()
                ->forApp($app)
                ->published()
                ->where('channel', ReleaseChannel::OpsApk->value)
                ->get()
                ->map(fn (AppRelease $release): array => $release->getAttributes())
                ->all(),
        );

        return $this->hydrate($rows);
    }

    /**
     * Releases store publiques et non archivées pour une plateforme et un
     * environnement donnés.
     *
     * @return Collection<int, AppRelease>
     */
    public function publishedStore(ReleasedApp $app, ReleasePlatform $platform, ReleaseEnvironment $environment): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::storeCacheKey($app, $platform, $environment),
            self::CACHE_TTL_SECONDS,
            fn (): array => AppRelease::query()
                ->forApp($app)
                ->published()
                ->where('channel', ReleaseChannel::Store->value)
                ->where('platform', $platform->value)
                ->where('environment', $environment->value)
                ->whereNotNull('publicly_confirmed_at')
                ->whereNotNull('rollout_completed_at')
                ->get()
                ->map(fn (AppRelease $release): array => $release->getAttributes())
                ->all(),
        );

        return $this->hydrate($rows);
    }

    public function latest(ReleasedApp $app): ?AppRelease
    {
        return $this->published($app)->first();
    }

    /**
     * Dernière release obligatoire publiée (seuil de version minimale).
     */
    public function latestMandatory(ReleasedApp $app): ?AppRelease
    {
        return $this->published($app)->first(fn (AppRelease $release): bool => $release->is_mandatory);
    }

    public function latestStore(ReleasedApp $app, ReleasePlatform $platform, ReleaseEnvironment $environment): ?AppRelease
    {
        return $this->publishedStore($app, $platform, $environment)->first();
    }

    public function check(ReleasedApp $app, ?AppVersion $installed): AppUpdateCheck
    {
        $latest = $this->latest($app);
        $mandatory = $this->latestMandatory($app);

        return new AppUpdateCheck(
            app: $app,
            installed: $installed,
            latest: $latest,
            mandatoryRelease: $mandatory,
            status: $this->statusFor($installed, $latest, $mandatory),
            channel: ReleaseChannel::OpsApk,
        );
    }

    public function checkStore(
        ReleasedApp $app,
        ReleasePlatform $platform,
        ReleaseEnvironment $environment,
        ?AppVersion $installed,
    ): AppUpdateCheck {
        $latest = $this->latestStore($app, $platform, $environment);
        $mandatory = $this->publishedStore($app, $platform, $environment)
            ->first(fn (AppRelease $release): bool => $release->is_mandatory);

        return new AppUpdateCheck(
            app: $app,
            installed: $installed,
            latest: $latest,
            mandatoryRelease: $mandatory,
            status: $this->statusFor($installed, $latest, $mandatory),
            platform: $platform,
            environment: $environment,
            channel: ReleaseChannel::Store,
        );
    }

    /**
     * Raccourci pour une version brute remontée par un appareil.
     */
    public function checkRaw(ReleasedApp $app, ?string $installedVersion): AppUpdateCheck
    {
        return $this->check($app, AppVersion::parse($installedVersion));
    }

    public function forget(ReleasedApp $app): void
    {
        Cache::forget(self::apkCacheKey($app));

        foreach (ReleasePlatform::cases() as $platform) {
            foreach (ReleaseEnvironment::cases() as $environment) {
                Cache::forget(self::storeCacheKey($app, $platform, $environment));
            }
        }
    }

    public static function apkCacheKey(ReleasedApp $app): string
    {
        return 'app-distribution:published-apk:'.$app->value;
    }

    public static function storeCacheKey(ReleasedApp $app, ReleasePlatform $platform, ReleaseEnvironment $environment): string
    {
        return sprintf('app-distribution:published-store:%s:%s:%s', $app->value, $platform->value, $environment->value);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, AppRelease>
     */
    private function hydrate(array $rows): Collection
    {
        $model = new AppRelease;
        $connection = $model->getConnection()->getName();

        return collect($rows)
            ->map(fn (array $attributes): AppRelease => $model->newFromBuilder($attributes, $connection))
            ->sort(fn (AppRelease $left, AppRelease $right): int => $right->appVersion()->compare($left->appVersion()))
            ->values();
    }

    private function statusFor(?AppVersion $installed, ?AppRelease $latest, ?AppRelease $mandatory): AppVersionStatus
    {
        if ($latest === null) {
            return AppVersionStatus::NotTracked;
        }

        if ($installed === null) {
            return AppVersionStatus::Unknown;
        }

        if ($mandatory !== null && $installed->isOlderThan($mandatory->appVersion())) {
            return AppVersionStatus::UpdateRequired;
        }

        if ($installed->isOlderThan($latest->appVersion())) {
            return AppVersionStatus::UpdateAvailable;
        }

        return AppVersionStatus::UpToDate;
    }
}
