<?php

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\AppDistribution\FakeApk;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Storage::fake(AppRelease::DISK);
    // Les assertions portent sur les props Inertia, pas sur les assets.
    $this->withoutVite();
});

function releaseAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

function releasePayload(array $overrides = []): array
{
    return [
        'app' => 'ops_mobile',
        'package' => FakeApk::make(),
        'version' => '1.4.0',
        'build_number' => 42,
        'release_notes' => "Correctifs d'impression du manifeste.",
        'is_mandatory' => false,
        ...$overrides,
    ];
}

it('forbids the releases screen to users without the app releases permission', function (): void {
    $director = User::factory()->create();
    $director->assignRole('operations_director');

    $this->actingAs($director)
        ->get(route('administration.releases'))
        ->assertForbidden();

    $this->actingAs($director)
        ->post(route('administration.releases.store'), releasePayload())
        ->assertForbidden();

    expect(AppRelease::query()->count())->toBe(0);
});

it('renders the releases screen with links, releases and fleet distribution for system admins', function (): void {
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();
    AppRelease::factory()->version('1.5.0', 50)->archived()->create();

    TerminalP501::factory()->create(['app_version' => '1.4.0+42']);
    TerminalP501::factory()->create(['app_version' => '1.3.5+38']);
    TerminalP501::factory()->create(['app_version' => '1.2.0+30']);
    TerminalP501::factory()->create(['app_version' => null]);
    TerminalP501::factory()->lost()->create(['app_version' => '1.0.0+1']);

    $this->actingAs(releaseAdmin())
        ->get(route('administration.releases'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/releases')
            ->where('selectedApp', 'ops_mobile')
            ->where('apps.0.value', 'ops_mobile')
            ->where('apps.0.releases_count', 3)
            ->where('links.download_page', route('downloads.apps.show', ['app' => 'ops-mobile']))
            ->where('links.latest_apk', route('downloads.apps.latest', ['app' => 'ops-mobile']))
            ->where('latest.version', '1.4.0')
            ->where('minimum.version', '1.3.0')
            ->has('releases', 3)
            ->where('releases.0.version', '1.5.0')
            ->where('releases.0.state', 'archived')
            ->where('releases.1.is_latest', true)
            ->where('releases.2.is_minimum', true)
            ->missing('releases.0.artifact_path')
            ->missing('releases.0.id')
            ->where('distribution.total', 4)
            ->where('distribution.by_status.up_to_date', 1)
            ->where('distribution.by_status.update_available', 1)
            ->where('distribution.by_status.update_required', 1)
            ->where('distribution.by_status.unknown', 1)
            ->where('constraints.max_package_kilobytes', 204_800)
            ->has('constraints.server_max_upload_bytes')
            ->where('permissions.canManage', true),
        );
});

it('publishes an uploaded apk with a server-computed sha256 and an audit entry', function (): void {
    $admin = releaseAdmin();
    $package = FakeApk::make();
    $expectedSha = hash_file('sha256', $package->getRealPath());
    $expectedSize = filesize($package->getRealPath());

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload([
            'package' => $package,
            'version' => 'v1.4.0',
            'is_mandatory' => '1',
            // Un sha256 envoyé par le client est ignoré : seul celui du serveur fait foi.
            'sha256' => str_repeat('0', 64),
        ]))
        ->assertRedirect(route('administration.releases', ['app' => 'ops_mobile']))
        ->assertSessionHasNoErrors();

    $release = AppRelease::query()->sole();

    expect($release->version)->toBe('1.4.0')
        ->and($release->build_number)->toBe(42)
        ->and($release->is_mandatory)->toBeTrue()
        ->and($release->sha256)->toBe($expectedSha)
        ->and($release->artifact_size)->toBe($expectedSize)
        ->and($release->published_at)->not->toBeNull()
        ->and($release->archived_at)->toBeNull()
        ->and($release->created_by)->toBe($admin->id)
        ->and($release->artifact_path)->toBe('ops_mobile/'.$release->uuid.'.apk');

    Storage::disk(AppRelease::DISK)->assertExists($release->artifact_path);

    $activity = Activity::query()->where('description', 'app_release_published')->sole();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($release->id)
        ->and($activity->properties['sha256'])->toBe($expectedSha)
        ->and($activity->properties['is_mandatory'])->toBeTrue();
});

it('rejects files that are not android packages', function (UploadedFile $package): void {
    $this->actingAs(releaseAdmin())
        ->post(route('administration.releases.store'), releasePayload(['package' => $package]))
        ->assertSessionHasErrors('package');

    expect(AppRelease::query()->count())->toBe(0);
    expect(Storage::disk(AppRelease::DISK)->allFiles())->toBe([]);
})->with([
    // Closures : le fichier temporaire est créé à l'exécution du test.
    'wrong extension' => fn (): UploadedFile => FakeApk::make('app-release.zip'),
    'not a zip archive' => fn (): UploadedFile => FakeApk::notAnArchive(),
    'zip without android manifest' => fn (): UploadedFile => FakeApk::zipWithoutManifest(),
]);

it('validates the semver format and the build number', function (): void {
    $admin = releaseAdmin();

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['version' => '1.4']))
        ->assertSessionHasErrors('version');

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['build_number' => 0]))
        ->assertSessionHasErrors('build_number');

    expect(AppRelease::query()->count())->toBe(0);
});

it('refuses a build number or a version lower than what was already published, archives included', function (): void {
    AppRelease::factory()->version('1.4.0', 42)->archived()->create();
    $admin = releaseAdmin();

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['version' => '1.5.0', 'build_number' => 42]))
        ->assertSessionHasErrors('build_number');

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['version' => '1.5.0', 'build_number' => 41]))
        ->assertSessionHasErrors('build_number');

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['version' => '1.3.9', 'build_number' => 43]))
        ->assertSessionHasErrors('version');

    $this->actingAs($admin)
        ->post(route('administration.releases.store'), releasePayload(['version' => '1.4.0', 'build_number' => 43]))
        ->assertSessionHasNoErrors();

    expect(AppRelease::query()->count())->toBe(2);
});

it('updates release notes and the mandatory flag with an audit entry', function (): void {
    $release = AppRelease::factory()->version('1.4.0', 42)->create(['release_notes' => 'Avant']);
    $admin = releaseAdmin();

    $this->actingAs($admin)
        ->patch(route('administration.releases.update', $release), [
            'release_notes' => 'Après',
            'is_mandatory' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($release->refresh()->release_notes)->toBe('Après')
        ->and($release->is_mandatory)->toBeTrue();

    $activity = Activity::query()->where('description', 'app_release_updated')->sole();

    expect($activity->properties['old']['is_mandatory'])->toBeFalse()
        ->and($activity->properties['attributes']['is_mandatory'])->toBeTrue();
});

it('archives and restores a release, which leaves then re-enters the latest computation', function (): void {
    Storage::disk(AppRelease::DISK)->put('ops_mobile/previous.apk', 'previous');
    Storage::disk(AppRelease::DISK)->put('ops_mobile/current.apk', 'current');
    $previous = AppRelease::factory()->version('1.3.0', 35)->create(['artifact_path' => 'ops_mobile/previous.apk']);
    $current = AppRelease::factory()->version('1.4.0', 42)->create(['artifact_path' => 'ops_mobile/current.apk']);
    $admin = releaseAdmin();

    $this->actingAs($admin)
        ->post(route('administration.releases.archive', $current))
        ->assertRedirect();

    expect($current->refresh()->archived_at)->not->toBeNull();

    $this->getJson('/api/v1/apps/ops-mobile/update?version=1.0.0')
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.3.0')
        ->assertJsonPath('data.release_uuid', $previous->uuid);

    $this->actingAs($admin)
        ->post(route('administration.releases.restore', $current))
        ->assertRedirect();

    expect($current->refresh()->archived_at)->toBeNull();

    $this->getJson('/api/v1/apps/ops-mobile/update?version=1.0.0')
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.4.0');

    expect(Activity::query()->where('description', 'app_release_archived')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'app_release_restored')->count())->toBe(1);
});

it('refuses to restore a release whose package is missing on disk', function (): void {
    $release = AppRelease::factory()->version('1.4.0', 42)->archived()->create();

    $this->actingAs(releaseAdmin())
        ->post(route('administration.releases.restore', $release))
        ->assertSessionHasErrors('release');

    expect($release->refresh()->archived_at)->not->toBeNull();
});
