<?php

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function storeReleaseAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

it('returns null when no confirmed store release exists for the triplet', function (): void {
    AppRelease::factory()->storeRelease(ReleasePlatform::Android)->version('1.4.0', 42)->create();

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=android&environment=production')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('exposes a confirmed store release without artifact fields or secrets', function (): void {
    AppRelease::factory()
        ->storeRelease(ReleasePlatform::Android)
        ->version('1.4.0', 42)
        ->confirmed()
        ->create(['release_notes' => 'Version stable sur le store.']);

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=android&environment=production&version=1.3.0&build=37')
        ->assertOk()
        ->assertJsonPath('data.app', 'passenger_mobile')
        ->assertJsonPath('data.channel', 'store')
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.environment', 'production')
        ->assertJsonPath('data.status', 'update_available')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.latest_version', '1.4.0')
        ->assertJsonPath('data.latest_build', 42)
        ->assertJsonPath('data.store_url', 'https://play.google.com/store/apps/details?id=app.kora.passenger')
        ->assertJsonMissing(['sha256', 'download_url', 'download_page_url', 'size_bytes']);
});

it('marks a newer mandatory store release as required', function (): void {
    AppRelease::factory()
        ->storeRelease(ReleasePlatform::Android)
        ->version('1.4.0', 42)
        ->mandatory()
        ->confirmed()
        ->create();

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=android&environment=production&version=1.3.0&build=37')
        ->assertOk()
        ->assertJsonPath('data.status', 'update_required')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.mandatory', true);
});

it('returns an up_to_date object instead of null when the installed version matches the latest release', function (): void {
    AppRelease::factory()
        ->storeRelease(ReleasePlatform::Ios)
        ->version('1.4.0', 42)
        ->confirmed()
        ->create();

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=ios&version=1.4.0&build=42')
        ->assertOk()
        ->assertJsonPath('data.status', 'up_to_date')
        ->assertJsonPath('data.update_available', false)
        ->assertJsonPath('data.mandatory', false);
});

it('reports unknown status without inventing installed version metadata', function (): void {
    AppRelease::factory()
        ->storeRelease(ReleasePlatform::Android)
        ->version('1.4.0', 42)
        ->confirmed()
        ->create();

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=android')
        ->assertOk()
        ->assertJsonPath('data.status', 'unknown')
        ->assertJsonPath('data.installed_version', null)
        ->assertJsonPath('data.installed_build', null);
});

it('rejects a store update check without platform and with an invalid platform', function (): void {
    $this->getJson('/api/v1/apps/passenger-mobile/update')
        ->assertStatus(422);

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=windows')
        ->assertStatus(422);
});

it('forbids non-manage users from creating, confirming or pushing store releases', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('conductor');

    $this->actingAs($operator)->post(route('administration.releases.store-store'), [
        'platform' => 'android',
        'environment' => 'production',
        'store_url' => 'https://play.google.com/store/apps/details?id=app.kora.passenger',
        'version' => '1.4.0',
        'build_number' => 42,
        'release_notes' => 'Nouvelle release.',
        'is_mandatory' => false,
    ])->assertForbidden();

    expect(AppRelease::query()->count())->toBe(0);
});

it('creates a store draft, confirms it, and creates one idempotent push campaign', function (): void {
    Queue::fake();
    $admin = storeReleaseAdmin();

    $this->actingAs($admin)->post(route('administration.releases.store-store'), [
        'platform' => 'android',
        'environment' => 'production',
        'store_url' => 'https://play.google.com/store/apps/details?id=app.kora.passenger',
        'version' => '1.4.0',
        'build_number' => 42,
        'release_notes' => 'Nouvelle release Passager.',
        'is_mandatory' => false,
    ])->assertRedirect(route('administration.releases', ['app' => 'passenger_mobile']))
        ->assertSessionHasNoErrors();

    $release = AppRelease::query()->sole();

    expect($release->channel->value)->toBe('store')
        ->and($release->platform->value)->toBe('android')
        ->and($release->environment->value)->toBe('production')
        ->and($release->publicly_confirmed_at)->toBeNull()
        ->and($release->rollout_completed_at)->toBeNull();

    $this->getJson('/api/v1/apps/passenger-mobile/update?platform=android')
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->actingAs($admin)->post(route('administration.releases.confirm-store', $release), [
        'rollout_completed' => true,
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('administration.releases.push-campaign', $release), [
        'title' => 'Mise à jour KORA Passager',
        'body' => 'La version 1.4.0 est disponible.',
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('administration.releases.push-campaign', $release), [
        'title' => 'Mise à jour KORA Passager',
        'body' => 'La version 1.4.0 est disponible.',
    ])->assertRedirect();

    expect(AppReleasePushCampaign::query()->where('release_id', $release->id)->count())->toBe(1);

    $campaign = AppReleasePushCampaign::query()->sole();

    expect($campaign->topic)->toBe('passenger-android-prod')
        ->and($campaign->status->value)->toBe('pending')
        ->and($campaign->attempts)->toBe(0);
});
