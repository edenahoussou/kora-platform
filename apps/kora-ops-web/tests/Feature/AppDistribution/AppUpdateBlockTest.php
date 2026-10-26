<?php

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{0: User, 1: string}
 */
function updateBlockConductor(): array
{
    $user = User::factory()->create();
    $user->assignRole('conductor');

    return [$user, $user->createToken('device:field', ['*'], now()->addDays(7))->plainTextToken];
}

it('adds a null update block to the handshake while no release is published, without touching the existing contract', function (): void {
    [$user, $token] = updateBlockConductor();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-UPD-001', 'assigned_user_id' => $user->id]);

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'P501-UPD-001', 'app_version' => '1.2.0+30'])
        ->assertOk()
        ->assertJsonPath('data.uuid', $terminal->uuid)
        ->assertJsonPath('data.app_version', '1.2.0+30')
        ->assertJsonPath('data.session.state', 'active')
        ->assertJsonPath('data.update', null);
});

it('returns a mandatory update block on handshake as soon as a newer mandatory release is published', function (): void {
    [$user, $token] = updateBlockConductor();
    TerminalP501::query()->create(['serial_number' => 'P501-UPD-002', 'assigned_user_id' => $user->id]);
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    $latest = AppRelease::factory()->version('1.4.0', 42)->create(['release_notes' => 'Nouveautés']);

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'P501-UPD-002', 'app_version' => '1.2.0+30'])
        ->assertOk()
        ->assertJsonPath('data.update.app', 'ops_mobile')
        ->assertJsonPath('data.update.status', 'update_required')
        ->assertJsonPath('data.update.mandatory', true)
        ->assertJsonPath('data.update.update_available', true)
        ->assertJsonPath('data.update.latest_version', '1.4.0')
        ->assertJsonPath('data.update.latest_build', 42)
        ->assertJsonPath('data.update.release_notes', 'Nouveautés')
        ->assertJsonPath('data.update.sha256', $latest->sha256)
        ->assertJsonPath('data.update.download_url', route('downloads.apps.release', ['app' => 'ops-mobile', 'release' => $latest->uuid]));
});

it('reports the freshly installed version as up to date on the next handshake', function (): void {
    [$user, $token] = updateBlockConductor();
    TerminalP501::query()->create(['serial_number' => 'P501-UPD-003', 'assigned_user_id' => $user->id, 'app_version' => '1.2.0+30']);
    AppRelease::factory()->version('1.4.0', 42)->mandatory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'P501-UPD-003', 'app_version' => '1.4.0+42'])
        ->assertOk()
        ->assertJsonPath('data.update.status', 'up_to_date')
        ->assertJsonPath('data.update.mandatory', false)
        ->assertJsonPath('data.update.update_available', false);
});

it('adds the update block to the sync response', function (): void {
    [, $token] = updateBlockConductor();
    TerminalP501::query()->create(['serial_number' => 'P501-UPD-004']);
    AppRelease::factory()->version('1.4.0', 42)->create();

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/sync', ['serial_number' => 'P501-UPD-004', 'app_version' => '1.3.0+35'])
        ->assertOk()
        ->assertJsonPath('data.serial_number', 'P501-UPD-004')
        ->assertJsonPath('data.app_version', '1.3.0+35')
        ->assertJsonPath('data.update.status', 'update_available')
        ->assertJsonPath('data.update.mandatory', false);
});

it('keeps activity at 204 without release and returns the update block once a release exists', function (): void {
    [, $token] = updateBlockConductor();
    TerminalP501::query()->create(['serial_number' => 'P501-UPD-005', 'app_version' => '1.0.0+1']);

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/activity', ['serial_number' => 'P501-UPD-005', 'type' => 'scan'])
        ->assertNoContent();

    AppRelease::factory()->version('1.4.0', 42)->mandatory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/ops-mobile/terminal/activity', ['serial_number' => 'P501-UPD-005', 'type' => 'scan'])
        ->assertOk()
        ->assertJsonPath('data.update.status', 'update_required')
        ->assertJsonPath('data.update.mandatory', true);
});

it('answers the public launch-time update check without authentication', function (): void {
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();

    $this->getJson('/api/v1/apps/ops-mobile/update?version=1.2.0&build=30')
        ->assertOk()
        ->assertJsonPath('data.status', 'update_required')
        ->assertJsonPath('data.installed_version', '1.2.0')
        ->assertJsonPath('data.installed_build', 30)
        ->assertJsonPath('data.minimum_version', '1.3.0');

    $this->getJson('/api/v1/apps/ops_mobile/update?version=1.3.0%2B35')
        ->assertOk()
        ->assertJsonPath('data.status', 'update_available')
        ->assertJsonPath('data.mandatory', false);

    $this->getJson('/api/v1/apps/unknown-app/update?version=1.0.0')->assertNotFound();
});

it('answers a null update block on the launch check while nothing is published', function (): void {
    $this->getJson('/api/v1/apps/ops-mobile/update?version=1.0.0')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('shows a server-computed version status for each terminal on the terminals screen', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    AppRelease::factory()->version('1.3.0', 35)->mandatory()->create();
    AppRelease::factory()->version('1.4.0', 42)->create();
    TerminalP501::factory()->create(['serial_number' => 'P501-A', 'app_version' => '1.4.0+42']);
    TerminalP501::factory()->create(['serial_number' => 'P501-B', 'app_version' => '1.3.0+35']);
    TerminalP501::factory()->create(['serial_number' => 'P501-C', 'app_version' => '1.2.0+30']);
    TerminalP501::factory()->create(['serial_number' => 'P501-D', 'app_version' => null]);

    $this->actingAs($admin)
        ->get(route('terminals'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('terminals')
            ->where('terminals.data.0.serial_number', 'P501-A')
            ->where('terminals.data.0.version_status.status', 'up_to_date')
            ->where('terminals.data.0.version_status.label', 'À jour')
            ->where('terminals.data.1.version_status.status', 'update_available')
            ->where('terminals.data.2.version_status.status', 'update_required')
            ->where('terminals.data.2.version_status.latest_version', '1.4.0')
            ->where('terminals.data.3.version_status.status', 'unknown'),
        );
});
