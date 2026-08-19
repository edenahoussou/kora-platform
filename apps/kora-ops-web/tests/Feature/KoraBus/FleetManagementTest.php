<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function fleetManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

function fleetReferences(): array
{
    return [
        Agency::query()->create(['code' => 'COT', 'name' => 'Agence Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']),
        BusModel::query()->create(['brand' => 'KORA', 'name' => 'Coach 55', 'default_seat_count' => 55, 'status' => 'active']),
        BusComposition::query()->create(['code' => '2+2-55', 'name' => 'Standard 2+2 — 55 places', 'seat_count' => 55, 'layout' => [['type' => 'standard', 'codes' => ['1A', '1B', '1C', '1D']], ['type' => 'crew', 'codes' => ['C1']]], 'status' => 'active']),
    ];
}

it('lists buses with their model, composition, and agency without exposing numeric ids', function (): void {
    [$agency, $model, $composition] = fleetReferences();
    $bus = Bus::query()->create(['registration_number' => 'BJ-9254-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'seat_count' => 55, 'status' => 'active']);

    $this->actingAs(fleetManager())->get(route('fleet.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('fleet')
        ->where('buses.data.0.uuid', $bus->uuid)
        ->where('buses.data.0.modelName', 'KORA Coach 55')
        ->where('buses.data.0.compositionName', 'Standard 2+2 — 55 places')
        ->where('buses.data.0.agencyName', 'Agence Cotonou')
        ->missing('buses.data.0.id')
        ->where('permissions.canCreate', true),
    );
});

it('creates a bus from a model and composition and generates its seats', function (): void {
    [$agency, $model, $composition] = fleetReferences();
    $user = fleetManager();

    $this->actingAs($user)->post(route('fleet.store'), [
        'registration_number' => ' bj-9254-rb ',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'agency_id' => $agency->id,
        'status' => 'draft',
    ])->assertRedirect(route('fleet.index', absolute: false));

    $bus = Bus::query()->where('registration_number', 'BJ-9254-RB')->firstOrFail();
    expect($bus->seat_count)->toBe(55)->and(Seat::query()->where('bus_id', $bus->id)->count())->toBe(5)->and(Seat::query()->where('bus_id', $bus->id)->where('sellable', false)->count())->toBe(1);
});

it('rejects duplicate registrations and unauthorized fleet access', function (): void {
    [$agency, $model, $composition] = fleetReferences();
    Bus::query()->create(['registration_number' => 'BJ-9254-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'seat_count' => 55, 'status' => 'active']);

    $this->actingAs(fleetManager())->post(route('fleet.store'), ['registration_number' => 'BJ-9254-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'status' => 'active'])->assertSessionHasErrors('registration_number');

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');
    $this->actingAs($agent)->get(route('fleet.index'))->assertForbidden();
});
