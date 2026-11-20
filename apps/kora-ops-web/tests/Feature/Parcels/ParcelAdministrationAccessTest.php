<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function parcelRoleUser(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

test('the seeded system administrator holds every parcel permission', function (): void {
    $admin = parcelRoleUser('system_admin');

    $permissions = [
        'parcels.assign',
        'parcels.configure',
        'parcels.customer',
        'parcels.finance',
        'parcels.handoff',
        'parcels.intake',
        'parcels.network',
        'parcels.override',
        'parcels.scan',
        'parcels.tariffs',
        'parcels.view',
    ];

    foreach ($permissions as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }
});

test('the sidebar parcel permission is limited to eligible roles', function (): void {
    $operationsDirector = parcelRoleUser('operations_director');
    $conductor = parcelRoleUser('conductor');

    expect($operationsDirector->can('parcels.network'))->toBeTrue()
        ->and($conductor->can('parcels.network'))->toBeFalse();
});

test('parcel operators can open the page and configure locations', function (): void {
    $operationsDirector = parcelRoleUser('operations_director');

    $this->actingAs($operationsDirector, 'web')
        ->get('/parcels')
        ->assertOk();

    $this->actingAs($operationsDirector, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.canConfigure', true)
        ->assertJsonPath('data.permissions.canPublishTariffs', false)
        ->assertJsonPath('data.permissions.canIntake', true);
});

test('a commercial director can publish tariffs without configuring corridors', function (): void {
    $commercialDirector = parcelRoleUser('commercial_director');

    $this->actingAs($commercialDirector, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.canConfigure', false)
        ->assertJsonPath('data.permissions.canPublishTariffs', true)
        ->assertJsonPath('data.permissions.canIntake', false);
});

test('network viewers can read configuration but cannot configure or archive', function (): void {
    $viewer = parcelRoleUser('general_management');
    $operationsDirector = parcelRoleUser('operations_director');
    [, $stop] = lineWithStops();
    $agency = Agency::factory()->create(['country' => 'BJ']);

    $this->actingAs($viewer, 'web')
        ->get('/parcels')
        ->assertOk();

    $this->actingAs($viewer, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.canConfigure', false);

    $hubUuid = $this->actingAs($operationsDirector, 'web')
        ->putJson('/parcels/data/hubs', [
            'agency_uuid' => $agency->uuid,
            'stop_uuid' => $stop->uuid,
            'enabled' => true,
        ])
        ->assertSuccessful()
        ->json('data.uuid');

    $this->actingAs($viewer, 'web')
        ->postJson("/parcels/data/hubs/{$hubUuid}/archive")
        ->assertForbidden();
});

test('users without the sidebar permission cannot open the parcel page', function (): void {
    $conductor = parcelRoleUser('conductor');

    $this->actingAs($conductor, 'web')
        ->get('/parcels')
        ->assertForbidden();

    $this->actingAs($conductor, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertForbidden();
});
