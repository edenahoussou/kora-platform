<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraServiceClassSeeder;
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

function makePhysicalStop(array $attributes = []): PhysicalStop
{
    return PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'PS-'.strtoupper(Str::random(6)),
        'name' => 'Gare test',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
        ...$attributes,
    ]);
}

function makeLine(array $attributes = []): Line
{
    return Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L'.strtoupper(Str::random(4)),
        'name' => 'Ligne test',
        'type' => 'nationale',
        'status' => 'active',
        ...$attributes,
    ]);
}

function makeStop(Line $line, PhysicalStop $physicalStop, int $order, array $attributes = []): Stop
{
    return Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'physical_stop_id' => $physicalStop->id,
        'name' => $physicalStop->name,
        'city' => $physicalStop->city,
        'country' => $physicalStop->country,
        'stop_order' => $order,
        'km_from_origin' => 0,
        'status' => 'active',
        ...$attributes,
    ]);
}

it('lists lines with stops count, physical stops catalogue and permissions without exposing numeric ids', function (): void {
    $manager = networkManager();
    $line = makeLine(['code' => 'L2', 'distance_km' => 420, 'estimated_duration_minutes' => 360]);
    $physicalStop = makePhysicalStop(['name' => 'Cotonou NM']);
    makeStop($line, $physicalStop, 1);

    $this->actingAs($manager)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('network')
            ->where('lines.0.uuid', $line->uuid)
            ->where('lines.0.code', 'L2')
            ->where('lines.0.type', 'nationale')
            ->where('lines.0.itinerary', 'Cotonou')
            ->where('lines.0.stopsCount', 1)
            ->where('lines.0.activeStopsCount', 1)
            ->where('lines.0.distanceKm', 420)
            ->missing('lines.0.id')
            ->where('physicalStops.0.uuid', $physicalStop->uuid)
            ->missing('physicalStops.0.id')
            ->where('permissions.canCreate', true));
});

it('creates a line with nested stops referencing existing physical stops', function (): void {
    $manager = networkManager();

    $cotonouPhysical = makePhysicalStop(['name' => 'Cotonou L5']);
    $ouidahPhysical = makePhysicalStop(['name' => 'Ouidah L5', 'city' => 'Ouidah']);

    $this->actingAs($manager)->post(route('network.store'), [
        'code' => ' l5 ',
        'name' => 'Ligne littorale',
        'type' => 'nationale',
        'status' => 'draft',
        'distance_km' => 42,
        'estimated_duration_minutes' => 60,
        'stops' => [
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'physical_stop_uuid' => $cotonouPhysical->uuid],
            ['name' => 'Ouidah', 'city' => 'Ouidah', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 42, 'physical_stop_uuid' => $ouidahPhysical->uuid],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $line = Line::query()->where('code', 'L5')->firstOrFail();
    expect($line->name)->toBe('Ligne littorale')
        ->and($line->stops()->count())->toBe(2)
        ->and($line->stops()->orderBy('stop_order')->first()->name)->toBe('Cotonou');
});

it('creates physical stops inline for map-placed points', function (): void {
    $manager = networkManager();

    $this->actingAs($manager)->post(route('network.store'), [
        'code' => 'L9',
        'name' => 'Ligne carte',
        'type' => 'nationale',
        'status' => 'draft',
        'stops' => [
            [
                'name' => 'Carrefour Savalou',
                'city' => 'Savalou',
                'country' => 'BJ',
                'stop_order' => 1,
                'km_from_origin' => 0,
                'latitude' => 7.9282,
                'longitude' => 1.9754,
                'physical_stop' => [
                    'name' => 'Carrefour Savalou',
                    'city' => 'Savalou',
                    'country' => 'BJ',
                    'latitude' => 7.9282,
                    'longitude' => 1.9754,
                ],
            ],
            [
                'name' => 'Terminal Dassa',
                'city' => 'Dassa-Zoumè',
                'country' => 'BJ',
                'stop_order' => 2,
                'km_from_origin' => 40,
                'latitude' => 7.7548,
                'longitude' => 2.1852,
                'physical_stop' => [
                    'name' => 'Terminal Dassa',
                    'city' => 'Dassa-Zoumè',
                    'country' => 'BJ',
                    'latitude' => 7.7548,
                    'longitude' => 2.1852,
                ],
            ],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $line = Line::query()->where('code', 'L9')->firstOrFail();
    $stop = $line->stops()->orderBy('stop_order')->firstOrFail();
    $physical = $stop->physicalStop()->firstOrFail();

    expect(PhysicalStop::query()->count())->toBe(2)
        ->and($physical->name)->toBe('Carrefour Savalou')
        ->and($physical->code)->toStartWith('PS-')
        ->and($physical->status->value)->toBe('active');
});

it('reuses an existing physical stop when the map point is within ~300 meters', function (): void {
    $manager = networkManager();
    $existing = makePhysicalStop([
        'name' => 'Gare de Bohicon',
        'city' => 'Bohicon',
        'latitude' => 7.1782,
        'longitude' => 2.0667,
    ]);

    $this->actingAs($manager)->post(route('network.store'), [
        'code' => 'L11',
        'name' => 'Ligne dédup',
        'type' => 'nationale',
        'status' => 'draft',
        'stops' => [
            [
                'name' => 'Bohicon ~11m',
                'city' => 'Bohicon',
                'country' => 'BJ',
                'stop_order' => 1,
                'km_from_origin' => 0,
                'latitude' => 7.17825,
                'longitude' => 2.06675,
                'physical_stop' => [
                    'name' => 'Bohicon ~11m',
                    'city' => 'Bohicon',
                    'country' => 'BJ',
                    'latitude' => 7.17825,
                    'longitude' => 2.06675,
                ],
            ],
            [
                'name' => 'Parakou',
                'city' => 'Parakou',
                'country' => 'BJ',
                'stop_order' => 2,
                'km_from_origin' => 290,
                'physical_stop_uuid' => makePhysicalStop(['name' => 'Parakou PS', 'city' => 'Parakou'])->uuid,
            ],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $line = Line::query()->where('code', 'L11')->firstOrFail();
    $firstStop = $line->stops()->orderBy('stop_order')->firstOrFail();

    expect($firstStop->physical_stop_id)->toBe($existing->id)
        ->and(PhysicalStop::query()->count())->toBe(2);
});

it('creates a bidirectional line and exposes the flag in the list', function (): void {
    $manager = networkManager();

    $cotonouPhysical = makePhysicalStop(['name' => 'Cotonou L7']);
    $parakouPhysical = makePhysicalStop(['name' => 'Parakou L7', 'city' => 'Parakou']);

    $this->actingAs($manager)->post(route('network.store'), [
        'code' => 'L7',
        'name' => 'Cotonou - Parakou',
        'type' => 'nationale',
        'bidirectional' => true,
        'status' => 'active',
        'stops' => [
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'physical_stop_uuid' => $cotonouPhysical->uuid],
            ['name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 600, 'physical_stop_uuid' => $parakouPhysical->uuid],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $line = Line::query()->where('code', 'L7')->firstOrFail();
    expect($line->bidirectional)->toBeTrue();

    $this->actingAs($manager)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.bidirectional', true));
});

it('defaults bidirectional to true when the field is omitted', function (): void {
    $this->actingAs(networkManager())->post(route('network.store'), [
        'code' => 'L8',
        'name' => 'Ligne par défaut',
        'type' => 'nationale',
        'status' => 'draft',
    ])->assertRedirect(route('network.index', absolute: false));

    expect(Line::query()->where('code', 'L8')->firstOrFail()->bidirectional)->toBeTrue();
});

it('rejects duplicate line codes', function (): void {
    makeLine(['code' => 'L2', 'name' => 'Existing']);

    $this->actingAs(networkManager())->post(route('network.store'), [
        'code' => 'L2',
        'name' => 'Duplicate',
        'type' => 'nationale',
        'status' => 'draft',
    ])->assertSessionHasErrors('code');
});

it('updates a line name and status', function (): void {
    $line = makeLine(['code' => 'L3', 'name' => 'Old name', 'status' => 'draft']);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'name' => 'New name',
        'status' => 'active',
    ])->assertRedirect(route('network.index', absolute: false));

    expect($line->refresh()->name)->toBe('New name')
        ->and($line->refresh()->status->value)->toBe('active');
});

it('synchronizes stops on update: update in place, create, delete and re-sequence', function (): void {
    $line = makeLine(['code' => 'L6']);
    $cotonouPhysical = makePhysicalStop(['name' => 'Cotonou L6']);
    $parakouPhysical = makePhysicalStop(['name' => 'Parakou L6', 'city' => 'Parakou']);
    $bohiconPhysical = makePhysicalStop(['name' => 'Bohicon L6', 'city' => 'Bohicon']);

    $stopA = makeStop($line, $cotonouPhysical, 1, ['name' => 'Cotonou', 'city' => 'Cotonou']);
    $stopB = makeStop($line, $parakouPhysical, 2, ['name' => 'Parakou', 'city' => 'Parakou', 'km_from_origin' => 420]);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'stops' => [
            // stopB remonté en première position et renommé (update in place)
            ['uuid' => $stopB->uuid, 'name' => 'Parakou Nord', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'latitude' => 9.3372, 'longitude' => 2.6303, 'physical_stop_uuid' => $parakouPhysical->uuid],
            // nouvel arrêt inline (création physique + stop)
            ['name' => 'Bohicon', 'city' => 'Bohicon', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 290, 'latitude' => 7.1782, 'longitude' => 2.0667, 'physical_stop_uuid' => $bohiconPhysical->uuid],
        ],
    ])->assertRedirect(route('network.index', absolute: false));

    $ordered = $line->stops()->orderBy('stop_order')->get();

    expect($ordered)->toHaveCount(2)
        ->and($ordered[0]->uuid)->toBe($stopB->uuid)
        ->and($ordered[0]->name)->toBe('Parakou Nord')
        ->and($ordered[0]->stop_order)->toBe(1)
        ->and($ordered[1]->name)->toBe('Bohicon')
        ->and($ordered[1]->stop_order)->toBe(2)
        ->and(Stop::withTrashed()->find($stopA->id)->trashed())->toBeTrue();
});

it('rejects stop synchronization when the line has published departures', function (): void {
    $line = makeLine(['code' => 'L12', 'name' => 'Avant départs']);
    $physical = makePhysicalStop(['name' => 'Cotonou L12']);
    makeStop($line, $physical, 1);

    $bus = Bus::query()->create([
        'registration_number' => 'AB-DEP-001',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 40,
        'status' => 'active',
    ]);
    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-01',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'name' => 'Renommée',
        'stops' => [
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'physical_stop_uuid' => $physical->uuid],
        ],
    ])->assertSessionHasErrors('stops');

    // La transaction globale annule aussi la métadonnée
    expect($line->refresh()->name)->toBe('Avant départs');
});

it('allows metadata update without touching stops when departures exist', function (): void {
    $line = makeLine(['code' => 'L13', 'name' => 'Nom initial']);
    $physical = makePhysicalStop(['name' => 'Cotonou L13']);
    makeStop($line, $physical, 1);

    $bus = Bus::query()->create([
        'registration_number' => 'AB-DEP-002',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 40,
        'status' => 'active',
    ]);
    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-02',
        'departure_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'name' => 'Nom mis à jour',
    ])->assertRedirect(route('network.index', absolute: false));

    expect($line->refresh()->name)->toBe('Nom mis à jour')
        ->and($line->stops()->count())->toBe(1);
});

it('rejects removing a stop that is referenced by a fare', function (): void {
    $this->seed(KoraServiceClassSeeder::class);

    $line = makeLine(['code' => 'L14']);
    $originPhysical = makePhysicalStop(['name' => 'Cotonou L14']);
    $destPhysical = makePhysicalStop(['name' => 'Parakou L14', 'city' => 'Parakou']);
    $origin = makeStop($line, $originPhysical, 1);
    $destination = makeStop($line, $destPhysical, 2, ['km_from_origin' => 420]);
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 5000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $this->actingAs(networkManager())->put(route('network.update', $line), [
        'stops' => [
            // destination omise mais référencée par un tarif
            ['uuid' => $origin->uuid, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'physical_stop_uuid' => $originPhysical->uuid],
        ],
    ])->assertSessionHasErrors('stops');

    expect($destination->refresh()->trashed())->toBeFalse();
});

it('rejects unauthorized network access for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('network.index'))->assertForbidden();
});

it('filters lines by type and builds itinerary from active stops', function (): void {
    $nationale = makeLine(['code' => 'L10', 'type' => 'nationale']);
    $cotonouPhysical = makePhysicalStop(['name' => 'Cotonou Filter NM']);
    $parakouPhysical = makePhysicalStop(['name' => 'Parakou Filter NM', 'city' => 'Parakou']);
    makeStop($nationale, $cotonouPhysical, 1);
    makeStop($nationale, $parakouPhysical, 2, ['km_from_origin' => 420]);

    makeLine(['code' => 'L20', 'name' => 'Internationale', 'type' => 'internationale']);

    $this->actingAs(networkManager())
        ->get(route('network.index', ['type' => 'nationale']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.code', 'L10')
            ->where('lines.0.itinerary', 'Cotonou → Parakou')
            ->where('lines.0.segmentsCount', 1)
            ->has('lines', 1));
});
