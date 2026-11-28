<?php

use App\Modules\AppDistribution\Data\AppUpdateCheck;
use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\AppVersionStatus;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Services\AppReleaseCatalog;
use Illuminate\Support\Facades\Cache;

function catalogCheck(?string $installed): AppUpdateCheck
{
    return app(AppReleaseCatalog::class)->checkRaw(ReleasedApp::OpsMobile, $installed);
}

it('reports not tracked when no release is published, and unknown without an installed version', function (): void {
    expect(catalogCheck('1.0.0+1')->status)->toBe(AppVersionStatus::NotTracked)
        ->and(catalogCheck('1.0.0+1')->toPayload())->toBeNull();

    AppRelease::factory()->version('1.0.0', 1)->create();

    expect(catalogCheck(null)->status)->toBe(AppVersionStatus::Unknown)
        ->and(catalogCheck('garbage')->status)->toBe(AppVersionStatus::Unknown);
});

it('picks the latest published release by semver precedence then build, ignoring archived and future ones', function (): void {
    AppRelease::factory()->version('1.9.0', 19)->create();
    $latest = AppRelease::factory()->version('1.10.0', 20)->create();
    AppRelease::factory()->version('1.11.0', 21)->archived()->create();
    AppRelease::factory()->version('1.12.0', 22)->create(['published_at' => now()->addDay()]);

    expect(app(AppReleaseCatalog::class)->latest(ReleasedApp::OpsMobile)?->is($latest))->toBeTrue();
});

it('forces the update when a newer mandatory release exists, even behind newer optional ones', function (): void {
    AppRelease::factory()->version('1.2.0', 30)->create();
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();

    $check = catalogCheck('1.2.0+30');

    expect($check->status)->toBe(AppVersionStatus::UpdateRequired)
        ->and($check->mandatory())->toBeTrue()
        ->and($check->updateAvailable())->toBeTrue()
        ->and($check->latest?->version)->toBe('1.4.0')
        ->and($check->mandatoryRelease?->version)->toBe('1.3.0');

    $payload = $check->toPayload();

    expect($payload)->toMatchArray([
        'status' => 'update_required',
        'latest_version' => '1.4.0',
        'latest_build' => 42,
        'mandatory' => true,
        'minimum_version' => '1.3.0',
        'minimum_build' => 35,
        'installed_version' => '1.2.0',
        'installed_build' => 30,
    ])->and($payload['download_url'])->toBe(route('downloads.apps.release', ['app' => 'ops-mobile', 'release' => $check->latest?->uuid]));
});

it('only proposes the update once the installed version has reached the last mandatory release', function (): void {
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();

    expect(catalogCheck('1.3.0+35')->status)->toBe(AppVersionStatus::UpdateAvailable)
        ->and(catalogCheck('1.3.0+35')->mandatory())->toBeFalse()
        ->and(catalogCheck('1.4.0+42')->status)->toBe(AppVersionStatus::UpToDate)
        ->and(catalogCheck('1.4.0')->status)->toBe(AppVersionStatus::UpToDate);
});

it('never asks a device ahead of the latest release (archived rollback) to downgrade', function (): void {
    AppRelease::factory()->version('1.4.0', 42)->create();
    AppRelease::factory()->version('1.5.0', 50)->mandatory()->archived()->create();

    $check = catalogCheck('1.5.0+50');

    expect($check->status)->toBe(AppVersionStatus::UpToDate)
        ->and($check->mandatory())->toBeFalse()
        ->and(catalogCheck('1.3.0+30')->mandatory())->toBeFalse();
});

it('refreshes the cached catalog as soon as a release is archived or its mandatory flag changes', function (): void {
    $release = AppRelease::factory()->version('1.4.0', 42)->create();

    expect(catalogCheck('1.0.0')->status)->toBe(AppVersionStatus::UpdateAvailable);

    $release->update(['is_mandatory' => true]);
    expect(catalogCheck('1.0.0')->status)->toBe(AppVersionStatus::UpdateRequired);

    $release->update(['archived_at' => now()]);
    expect(catalogCheck('1.0.0')->status)->toBe(AppVersionStatus::NotTracked);
});

it('parses the version formats reported by devices', function (string $raw, ?string $expected): void {
    expect(AppVersion::parse($raw)?->__toString())->toBe($expected);
})->with([
    ['1.4.0', '1.4.0'],
    ['v1.4.0', '1.4.0'],
    ['1.4.0+42', '1.4.0+42'],
    ['1.4.0 (42)', '1.4.0+42'],
    ['1.4.0-beta.1+42', '1.4.0-beta.1+42'],
    ['1.4', '1.4.0'],
    ['', null],
    ['latest', null],
]);

it('compares versions with semver precedence then build number', function (string $left, string $right, int $expected): void {
    expect(AppVersion::parse($left)?->compare(AppVersion::parse($right)))->toBe($expected);
})->with([
    ['1.10.0', '1.9.0', 1],
    ['1.4.0+42', '1.4.0+41', 1],
    ['1.4.0', '1.4.0+41', 0],
    ['1.4.0-beta.1', '1.4.0', -1],
    ['1.4.0-beta.2', '1.4.0-beta.10', -1],
    ['1.4.0-alpha', '1.4.0-1', 1],
    ['2.0.0+1', '1.99.99+999', 1],
]);

it('reads the cached catalog back from a serializing cache store without incomplete objects', function (): void {
    config(['cache.default' => 'file']);
    Cache::forget(AppReleaseCatalog::apkCacheKey(ReleasedApp::OpsMobile));
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();

    // 1er appel : remplit le cache ; 2e appel : relit depuis le fichier.
    catalogCheck('1.2.0');
    $check = catalogCheck('1.2.0');

    expect($check->status)->toBe(AppVersionStatus::UpdateRequired)
        ->and($check->latest)->toBeInstanceOf(AppRelease::class)
        ->and($check->latest?->app)->toBe(ReleasedApp::OpsMobile)
        ->and($check->latest?->published_at)->not->toBeNull();

    Cache::forget(AppReleaseCatalog::apkCacheKey(ReleasedApp::OpsMobile));
});
