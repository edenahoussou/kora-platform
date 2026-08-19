<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function networkManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

it('lists lines with stops count and permissions without exposing numeric ids', function (): void {
    $manager = networkManager();
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Ligne nationale',
        'type' => 'nationale',
        'status' => 'active',
        'distance_km' => 420,
        'estimated_duration_minutes' => 360,
    ]);
    Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('network')
            ->where('lines.data.0.uuid', $line->uuid)
            ->where('lines.data.0.code', 'L2')
            ->where('lines.data.0.type', 'nationale')
            ->where('lines.data.0.itinerary', 'Cotonou')
            ->where('lines.data.0.stopsCount', 1)
            ->where('lines.data.0.activeStopsCount', 1)
            ->where('lines.data.0.segmentsCount', 0)
            ->where('lines.data.0.distanceKm', 420)
            ->missing('lines.data.0.id')
            ->where('permissions.canCreate', true));
});

it('creates a line with nested stops', function (): void {
    $manager = networkManager();

    $this->actingAs($manager)->post(route('network.store'), [
        'code' => ' l5 ',
        'name' => 'Ligne littorale',
        'type' => 'nationale',
        'status' => 'draft',
        'distance_km' => 42,
        'estimated_duration_minutes' => 60,
        'stops' => [
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
            ['name' => 'Ouidah', 'city' => 'Ouidah', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 42],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $line = Line::query()->where('code', 'L5')->firstOrFail();
    expect($line->name)->toBe('Ligne littorale')
        ->and($line->stops()->count())->toBe(2)
        ->and($line->stops()->orderBy('stop_order')->first()->name)->toBe('Cotonou');
});

it('rejects duplicate line codes', function (): void {
    Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Existing',
        'status' => 'active',
    ]);

    $this->actingAs(networkManager())->post(route('network.store'), [
        'code' => 'L2',
        'name' => 'Duplicate',
        'type' => 'nationale',
        'status' => 'draft',
    ])->assertSessionHasErrors('code');
});

it('updates a line name and status', function (): void {
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L3',
        'name' => 'Old name',
        'status' => 'draft',
    ]);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'name' => 'New name',
        'status' => 'active',
    ])->assertRedirect(route('network.index', absolute: false));

    expect($line->refresh()->name)->toBe('New name')
        ->and($line->refresh()->status->value)->toBe('active');
});

it('shows line detail with ordered stops', function (): void {
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Nationale',
        'status' => 'active',
    ]);
    Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 420, 'status' => 'active']);
    Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);

    $this->actingAs(networkManager())
        ->get(route('network.show', $line))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('network/detail')
            ->where('line.uuid', $line->uuid)
            ->where('line.stops.0.name', 'Cotonou')
            ->where('line.stops.1.name', 'Parakou')
            ->missing('line.id'));
});

it('adds a stop to a line', function (): void {
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Nationale',
        'status' => 'active',
    ]);

    $this->actingAs(networkManager())->post(route('network.stops.store', $line), [
        'name' => 'Bohicon',
        'city' => 'Bohicon',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 121,
    ])->assertRedirect(route('network.show', $line, absolute: false));

    expect($line->stops()->count())->toBe(1)
        ->and($line->stops()->first()->name)->toBe('Bohicon');
});

it('rejects duplicate stop order on the same line', function (): void {
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Nationale',
        'status' => 'active',
    ]);
    Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);

    $this->actingAs(networkManager())->post(route('network.stops.store', $line), [
        'name' => 'Allada',
        'city' => 'Allada',
        'country' => 'BJ',
        'stop_order' => 1,
    ])->assertSessionHasErrors('stop_order');
});

it('deactivates a stop', function (): void {
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L2',
        'name' => 'Nationale',
        'status' => 'active',
    ]);
    $stop = Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);

    $this->actingAs(networkManager())->patch(route('network.stops.deactivate', [$line, $stop]))
        ->assertRedirect(route('network.show', $line, absolute: false));

    expect($stop->refresh()->status->value)->toBe('archived');
});

it('rejects unauthorized network access for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('network.index'))->assertForbidden();
});

it('filters lines by type and builds itinerary from active stops', function (): void {
    $nationale = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L10',
        'name' => 'Nationale',
        'type' => 'nationale',
        'status' => 'active',
    ]);
    Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $nationale->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);
    Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $nationale->id, 'name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 420, 'status' => 'active']);

    $internationale = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L20',
        'name' => 'Internationale',
        'type' => 'internationale',
        'status' => 'active',
    ]);

    $this->actingAs(networkManager())
        ->get(route('network.index', ['type' => 'nationale']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lines.data.0.code', 'L10')
            ->where('lines.data.0.itinerary', 'Cotonou → Parakou')
            ->where('lines.data.0.segmentsCount', 1)
            ->where('lines.total', 1));
});
