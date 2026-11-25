<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Models\ParcelHub;
use Database\Seeders\KoraBusRolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
});

function permissionHub(Agency $agency): ParcelHub
{
    [$line, $stop] = lineWithStops();

    return ParcelHub::query()->create([
        'agency_id' => $agency->id,
        'stop_id' => $stop->id,
        'enabled' => true,
        'supports_weighing' => true,
    ]);
}

it('attribue au rôle KORA POINT les permissions Colis du cahier des charges', function (): void {
    $agent = User::factory()->create()->assignRole('kora_point_agent');

    expect($agent->can('parcels.view'))->toBeTrue()
        ->and($agent->can('parcels.intake'))->toBeTrue()
        ->and($agent->can('parcels.configure'))->toBeFalse()
        ->and($agent->can('parcels.finance'))->toBeFalse();
});

it('donne toutes les permissions Colis à l’administrateur système', function (): void {
    $admin = User::factory()->create()->assignRole('system_admin');

    foreach (SystemRole::allPermissions() as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }
});

it('réserve le manifeste aux agences et à l’exploitation, pas aux directions en lecture seule', function (): void {
    $agent = User::factory()->create()->assignRole('agency_agent');
    $manager = User::factory()->create()->assignRole('agency_manager');
    $operations = User::factory()->create()->assignRole('operations_director');
    $commercial = User::factory()->create()->assignRole('commercial_director');
    $daf = User::factory()->create()->assignRole('daf');
    $management = User::factory()->create()->assignRole('general_management');

    expect($agent->can('parcels.manifest'))->toBeTrue()
        ->and($manager->can('parcels.manifest'))->toBeTrue()
        ->and($operations->can('parcels.manifest'))->toBeTrue()
        ->and($commercial->can('parcels.manifest'))->toBeFalse()
        ->and($daf->can('parcels.manifest'))->toBeFalse()
        ->and($management->can('parcels.manifest'))->toBeFalse();
});

it('ouvre la configuration Colis en lecture aux rôles terrain sans droits de configuration', function (): void {
    $agency = Agency::factory()->create(['country' => 'BJ']);
    $ownHub = permissionHub($agency);
    $otherHub = permissionHub(Agency::factory()->create(['country' => 'BJ']));

    $agent = User::factory()->create(['agency_id' => $agency->id])
        ->assignRole('kora_point_agent');

    $response = $this->actingAs($agent)->getJson('/parcels/data/configuration');

    $response->assertOk()
        ->assertJsonPath('data.permissions.canConfigure', false)
        ->assertJsonPath('data.permissions.canPublishTariffs', false)
        ->assertJsonPath('data.permissions.canIntake', true);

    $hubs = collect($response->json('data.hubs'));
    expect($hubs->firstWhere('uuid', $ownHub->uuid)['deposit_allowed'])->toBeTrue()
        ->and($hubs->firstWhere('uuid', $otherHub->uuid))->toBeNull();
});

it('refuse une action Colis avant validation lorsque la permission manque', function (): void {
    $viewer = User::factory()->create()->assignRole('general_management');

    $this->actingAs($viewer)
        ->postJson('/parcels/data/quote', [])
        ->assertForbidden();
});

it('laisse valider une action Colis autorisée par la permission', function (): void {
    $agent = User::factory()->create()->assignRole('agency_agent');

    $this->actingAs($agent)
        ->postJson('/parcels/data/quote', [])
        ->assertUnprocessable();
});

it('refuse le menu Colis à un rôle sans permission Colis', function (): void {
    $conductor = User::factory()->create()->assignRole('conductor');

    $this->actingAs($conductor)
        ->get('/parcels')
        ->assertForbidden();
});
