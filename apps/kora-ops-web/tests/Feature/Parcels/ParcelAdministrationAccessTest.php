<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->get('/sale/parcels')
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
        ->get('/sale/parcels')
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

test('agency and point operators cannot open the administration screen', function (): void {
    $agency = Agency::factory()->create(['country' => 'BJ']);
    $partnerAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
    $agencyAgent = User::factory()->create(['agency_id' => $agency->id])->assignRole('agency_agent');
    $pointAgent = User::factory()->create(['agency_id' => $partnerAgency->id])->assignRole('kora_point_agent');
    $unassignedPointAgent = User::factory()->create()->assignRole('kora_point_agent');

    $this->actingAs($agencyAgent, 'web')
        ->get('/sale/parcels')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sale-parcels-index'));

    $this->actingAs($agencyAgent, 'web')
        ->get('/administration/parcels')
        ->assertForbidden();

    $this->actingAs($agencyAgent, 'web')
        ->get('/kora-point/parcels')
        ->assertForbidden();

    $this->actingAs($pointAgent, 'web')
        ->get('/kora-point/parcels')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sale-parcels'));

    $this->actingAs($pointAgent, 'web')
        ->get('/administration/parcels')
        ->assertForbidden();

    $this->actingAs($unassignedPointAgent, 'web')
        ->get('/kora-point/parcels')
        ->assertForbidden();
});

test('network roles can read the administration screen and capacity projection', function (): void {
    foreach (['operations_director', 'commercial_director', 'daf', 'general_management'] as $role) {
        $actor = parcelRoleUser($role);

        $this->actingAs($actor, 'web')
            ->get('/administration/parcels')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin-parcels'));

        $this->actingAs($actor, 'web')
            ->getJson('/parcels/data/capacity')
            ->assertSuccessful();
    }
});

test('an agency operator receives only operational corridors and the current tariff', function (): void {
    [, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $otherOriginAgency = Agency::factory()->create(['country' => 'BJ']);
    $otherDestinationAgency = Agency::factory()->create(['country' => 'BJ']);

    $origin = ParcelHub::query()->create([
        'agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true,
    ]);
    $destination = ParcelHub::query()->create([
        'agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true,
    ]);
    $otherOrigin = ParcelHub::query()->create([
        'agency_id' => $otherOriginAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true,
    ]);
    $otherDestination = ParcelHub::query()->create([
        'agency_id' => $otherDestinationAgency->id, 'stop_id' => $originStop->id, 'enabled' => true,
    ]);

    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => true,
    ]);
    $otherCorridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $otherOrigin->id, 'destination_hub_id' => $otherDestination->id, 'enabled' => true,
    ]);
    $corridor->tariffs()->create([
        'base_amount' => 1500, 'return_amount' => 600, 'created_by' => User::factory()->create()->id,
    ]);
    $corridor->tariffs()->create([
        'base_amount' => 2500, 'return_amount' => 600, 'version' => 2, 'created_by' => User::factory()->create()->id,
    ]);

    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $payload = $this->actingAs($agent, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertSuccessful()
        ->json('data');

    expect(collect($payload['corridors'])->pluck('uuid')->all())->toBe([$corridor->uuid])
        ->and(collect($payload['hubs'])->pluck('uuid')->all())->toContain($origin->uuid, $destination->uuid)
        ->and(collect($payload['hubs'])->pluck('uuid')->all())->not->toContain($otherOrigin->uuid, $otherDestination->uuid)
        ->and(collect($payload['corridors'])->pluck('uuid')->all())->not->toContain($otherCorridor->uuid)
        ->and($payload['corridors'][0]['tariffs'])->toHaveCount(1)
        ->and($payload['corridors'][0]['tariffs'][0]['base_amount'])->toBe(2500)
        ->and($payload['options']['internal_agencies'])->toBe([])
        ->and($payload['options']['partner_points'])->toBe([])
        ->and($payload['permissions']['canConfigure'])->toBeFalse();
});
