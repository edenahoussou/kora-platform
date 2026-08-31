<?php

use App\Models\User;
use App\Modules\BusNetwork\Actions\CreateBusAction;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Models\Departure;
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

it('filters buses by search term, status, and agency', function (): void {
    [$agency1, $model, $composition] = fleetReferences();
    $agency2 = Agency::query()->create(['code' => 'PAR', 'name' => 'Agence Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active']);

    $bus1 = Bus::query()->create(['registration_number' => 'BJ-1111-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency1->id, 'seat_count' => 55, 'status' => 'active']);
    $bus2 = Bus::query()->create(['registration_number' => 'BJ-2222-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency2->id, 'seat_count' => 55, 'status' => 'maintenance']);

    // Filter by search query
    $this->actingAs(fleetManager())->get(route('fleet.index', ['q' => '1111']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buses.total', 1)
            ->where('buses.data.0.uuid', $bus1->uuid),
        );

    // Filter by status
    $this->actingAs(fleetManager())->get(route('fleet.index', ['status' => 'maintenance']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buses.total', 1)
            ->where('buses.data.0.uuid', $bus2->uuid),
        );

    // Filter by agency
    $this->actingAs(fleetManager())->get(route('fleet.index', ['agency_id' => $agency2->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buses.total', 1)
            ->where('buses.data.0.uuid', $bus2->uuid),
        );
});

it('shows bus detail page with seats, departures, and timeline without exposing numeric ids', function (): void {
    [$agency, $model, $composition] = fleetReferences();

    $createBusAction = app(CreateBusAction::class);
    $bus = $createBusAction->execute([
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'agency_id' => $agency->id,
        'status' => 'active',
    ]);

    $line = Line::query()->create([
        'code' => 'L1',
        'name' => 'Cotonou - Abidjan',
        'distance_km' => 1020,
        'status' => 'active',
    ]);

    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-08-25',
        'departure_time' => '07:30',
        'status' => 'scheduled',
    ]);

    $this->actingAs(fleetManager())->get(route('fleet.show', $bus))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('fleet/detail')
        ->where('bus.uuid', $bus->uuid)
        ->where('bus.registrationNumber', 'BJ-9254-RB')
        ->where('bus.modelName', 'KORA Coach 55')
        ->where('bus.compositionName', 'Standard 2+2 — 55 places')
        ->where('bus.agencyName', 'Agence Cotonou')
        ->where('bus.seatCount', 55)
        ->where('bus.seats.0.code', '1A')
        ->where('bus.seats.0.sellable', true)
        ->where('bus.departures.0.lineCode', 'L1')
        ->where('bus.departures.0.departureTime', fn (string $time): bool => substr($time, 0, 5) === '07:30')
        ->missing('bus.id')
        ->missing('bus.seats.0.id')
        ->missing('bus.departures.0.id')
        ->where('permissions.canUpdate', true)
        ->where('permissions.canDeactivate', true),
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

it('updates a bus and resyncs seats when composition changes', function (): void {
    [$agency, $model, $composition1] = fleetReferences();
    $composition2 = BusComposition::query()->create([
        'code' => '2+1-30',
        'name' => 'Premium 2+1 — 30 places',
        'seat_count' => 30,
        'layout' => [['type' => 'premium', 'codes' => ['P1', 'P2', 'P3']]],
        'status' => 'active',
    ]);

    $createBusAction = app(CreateBusAction::class);
    $bus = $createBusAction->execute([
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition1->id,
        'agency_id' => $agency->id,
        'status' => 'active',
    ]);

    expect(Seat::query()->where('bus_id', $bus->id)->count())->toBe(5);

    $this->actingAs(fleetManager())->put(route('fleet.update', $bus), [
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition2->id,
        'agency_id' => $agency->id,
        'status' => 'active',
    ])->assertRedirect(route('fleet.index', absolute: false));

    $bus->refresh();
    expect($bus->composition_id)->toBe($composition2->id)
        ->and($bus->seat_count)->toBe(30)
        ->and(Seat::query()->where('bus_id', $bus->id)->count())->toBe(3);
});

it('prevents composition change when bus already has departures', function (): void {
    [$agency, $model, $composition1] = fleetReferences();
    $composition2 = BusComposition::query()->create([
        'code' => '2+1-30',
        'name' => 'Premium 2+1 — 30 places',
        'seat_count' => 30,
        'layout' => [['type' => 'premium', 'codes' => ['P1', 'P2', 'P3']]],
        'status' => 'active',
    ]);

    $createBusAction = app(CreateBusAction::class);
    $bus = $createBusAction->execute([
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition1->id,
        'agency_id' => $agency->id,
        'status' => 'active',
    ]);

    $line = Line::query()->create([
        'code' => 'L1',
        'name' => 'Cotonou - Abidjan',
        'distance_km' => 1020,
        'status' => 'active',
    ]);

    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-08-25',
        'departure_time' => '07:30',
        'status' => 'scheduled',
    ]);

    $this->actingAs(fleetManager())->put(route('fleet.update', $bus), [
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition2->id,
        'agency_id' => $agency->id,
        'status' => 'active',
    ])->assertSessionHasErrors('composition_id');
});

it('changes bus status through status endpoint', function (): void {
    [$agency, $model, $composition] = fleetReferences();
    $bus = Bus::query()->create([
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'agency_id' => $agency->id,
        'seat_count' => 55,
        'status' => 'active',
    ]);

    $this->actingAs(fleetManager())->patch(route('fleet.status', $bus), [
        'status' => 'maintenance',
    ])->assertRedirect(route('fleet.index', absolute: false));

    expect($bus->refresh()->status->value)->toBe('maintenance');

    $this->actingAs(fleetManager())->patch(route('fleet.status', $bus), [
        'status' => 'retired',
    ])->assertRedirect(route('fleet.index', absolute: false));

    expect($bus->refresh()->status->value)->toBe('retired');
});

it('rejects duplicate registrations and unauthorized fleet access', function (): void {
    [$agency, $model, $composition] = fleetReferences();
    Bus::query()->create(['registration_number' => 'BJ-9254-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'seat_count' => 55, 'status' => 'active']);

    $this->actingAs(fleetManager())->post(route('fleet.store'), ['registration_number' => 'BJ-9254-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'status' => 'active'])->assertSessionHasErrors('registration_number');

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');
    $this->actingAs($agent)->get(route('fleet.index'))->assertForbidden();
    $this->actingAs($agent)->get(route('fleet.show', Bus::first()))->assertForbidden();
    $this->actingAs($agent)->post(route('fleet.store'), ['registration_number' => 'BJ-0000-RB', 'bus_model_id' => $model->id, 'composition_id' => $composition->id, 'agency_id' => $agency->id, 'status' => 'active'])->assertForbidden();
});
