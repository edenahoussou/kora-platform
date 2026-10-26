<?php

namespace App\Modules\AppDistribution\Services;

use App\Modules\AppDistribution\Data\AppUpdateCheck;
use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\AppVersionStatus;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Source unique du calcul « dernière version / version obligatoire » d'une
 * application distribuée hors store (MEZ-150).
 *
 * Appelé à chaque handshake/sync/activity des terminaux : la liste des
 * releases publiées est donc mise en cache (quelques lignes par app) et
 * invalidée à chaque écriture sur `app_releases` (voir `AppRelease::booted`).
 * Le tri se fait en PHP selon la précédence semver + build, qu'un ORDER BY
 * SQL ne sait pas exprimer.
 */
class AppReleaseCatalog
{
    private const CACHE_TTL_SECONDS = 600;

    /**
     * Releases publiées et non archivées, de la plus récente à la plus
     * ancienne.
     *
     * @return Collection<int, AppRelease>
     */
    public function published(ReleasedApp $app): Collection
    {
        // On met en cache les attributs bruts (tableaux scalaires), jamais les
        // modèles : Laravel 13 refuse de désérialiser des objets depuis le
        // cache (`cache.serializable_classes`), ce qui produirait des
        // __PHP_Incomplete_Class. Les modèles sont réhydratés ci-dessous.
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            self::cacheKey($app),
            self::CACHE_TTL_SECONDS,
            fn (): array => AppRelease::query()
                ->forApp($app)
                ->published()
                ->get()
                ->map(fn (AppRelease $release): array => $release->getAttributes())
                ->all(),
        );

        $model = new AppRelease;
        $connection = $model->getConnection()->getName();

        return collect($rows)
            ->map(fn (array $attributes): AppRelease => $model->newFromBuilder($attributes, $connection))
            ->sort(fn (AppRelease $left, AppRelease $right): int => $right->appVersion()->compare($left->appVersion()))
            ->values();
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
        );
    }

    /**
     * Raccourci pour une version brute remontée par un appareil
     * (`app_version` des terminaux, `1.4.0+42`).
     */
    public function checkRaw(ReleasedApp $app, ?string $installedVersion): AppUpdateCheck
    {
        return $this->check($app, AppVersion::parse($installedVersion));
    }

    public function forget(ReleasedApp $app): void
    {
        Cache::forget(self::cacheKey($app));
    }

    public static function cacheKey(ReleasedApp $app): string
    {
        return 'app-distribution:published-releases:'.$app->value;
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
