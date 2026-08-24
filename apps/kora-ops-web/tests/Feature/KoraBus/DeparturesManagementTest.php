<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function departuresManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

function agencyAgent(): User
{
    $user = User::factory()->create();
    $user->assignRole('agency_agent');

    return $user;
}

/**
 * @return array{0: Agency, 1: Line, 2: Bus, 3: BusModel, 4: BusComposition, 5: User, 6: User}
 */
function departuresReferences(): array
{
    $agency = Agency::query()->create([
        'code' => 'COT',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $line = Line::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'L1',
        'name' => 'Cotonou - Abidjan',
        'type' => 'internationale',
        'countries_crossed' => ['BJ', 'TG', 'GH', 'CI'],
        'distance_km' => 1020,
        'estimated_duration_minutes' => 1260,
        'status' => 'active',
    ]);

    foreach ([
        ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
        ['name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 35],
        ['name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'stop_order' => 3, 'km_from_origin' => 1020],
    ] as $stop) {
        Stop::query()->create($stop + ['uuid' => (string) Str::uuid(), 'line_id' => $line->id, 'status' => 'active']);
    }

    $model = BusModel::query()->create([
        'uuid' => (string) Str::uuid(),
        'brand' => 'KORA',
        'name' => 'Coach 55',
        'default_seat_count' => 55,
        'status' => 'active',
    ]);

    $composition = BusComposition::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => '2+2-55',
        'name' => 'Standard 2+2 — 55 places',
        'seat_count' => 55,
        'layout' => [['type' => 'standard', 'codes' => ['1A', '1B']]],
        'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-9254-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'agency_id' => $agency->id,
        'brand' => 'KORA',
        'model' => 'Coach 55',
        'seat_count' => 55,
        'status' => 'active',
    ]);

    $driver = User::factory()->create(['name' => 'Jean Driver']);
    $driver->assignRole('bus_driver');

    $conductor = User::factory()->create(['name' => 'Marc Conductor']);
    $conductor->assignRole('conductor');

    return [$agency, $line, $bus, $model, $composition, $driver, $conductor];
}

it('lists departures with line, bus, agency, and crew without exposing numeric ids', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'estimated_arrival_at' => Carbon::today()->setTime(7, 30)->addMinutes(1260),
        'driver_id' => $driver->id,
        'conductor_id' => $conductor->id,
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departures')
            ->where('departures.data.0.uuid', $departure->uuid)
            ->where('departures.data.0.lineCode', 'L1')
            ->where('departures.data.0.lineName', 'Cotonou - Abidjan')
            ->where('departures.data.0.busRegistration', 'BJ-9254-RB')
            ->where('departures.data.0.agencyName', 'Agence Cotonou')
            ->where('departures.data.0.driverName', 'Jean Driver')
            ->where('departures.data.0.conductorName', 'Marc Conductor')
            ->where('departures.data.0.status', 'scheduled')
            ->missing('departures.data.0.id')
            ->where('permissions.canCreate', true),
        );
});

it('filters departures by status, agency, and search term', function (): void {
    [$agency1, $line, $bus1, $model, $composition] = departuresReferences();

    $agency2 = Agency::query()->create([
        'code' => 'PAR',
        'name' => 'Agence Parakou',
        'city' => 'Parakou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $bus2 = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-2222-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'agency_id' => $agency2->id,
        'brand' => 'KORA',
        'model' => 'Coach 55',
        'seat_count' => 55,
        'status' => 'active',
    ]);

    $departure1 = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus1->id,
        'agency_id' => $agency1->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $departure2 = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus2->id,
        'agency_id' => $agency2->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '10:30',
        'status' => DepartureStatus::Boarding->value,
    ]);

    // Filter by status
    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['status' => 'boarding']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $departure2->uuid),
        );

    // Filter by agency
    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['agency_uuid' => $agency2->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $departure2->uuid),
        );

    // Filter by search term (bus registration)
    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['q' => '2222']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $departure2->uuid),
        );
});

it('shows a departure detail with line stops, bus, agency, and crew', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'estimated_arrival_at' => Carbon::today()->setTime(7, 30)->addMinutes(1260),
        'driver_id' => $driver->id,
        'conductor_id' => $conductor->id,
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departures/detail')
            ->where('departure.uuid', $departure->uuid)
            ->where('departure.lineCode', 'L1')
            ->where('departure.lineName', 'Cotonou - Abidjan')
            ->where('departure.busRegistration', 'BJ-9254-RB')
            ->where('departure.agencyName', 'Agence Cotonou')
            ->where('departure.driverName', 'Jean Driver')
            ->where('departure.conductorName', 'Marc Conductor')
            ->where('departure.status', 'scheduled')
            ->missing('departure.id')
            ->has('departure.stops', 3),
        );
});

it('creates a departure and computes estimated arrival from line duration', function (): void {
    [$agency, $line, $bus] = departuresReferences();
    $manager = departuresManager();

    $response = $this->actingAs($manager)->post(route('departures.store'), [
        'line_uuid' => $line->uuid,
        'bus_uuid' => $bus->uuid,
        'agency_uuid' => $agency->uuid,
        'departure_date' => Carbon::today()->addDay()->format('Y-m-d'),
        'departure_time' => '08:00',
        'driver_uuid' => null,
        'conductor_uuid' => null,
    ]);

    $response->assertRedirect(route('departures.index'));

    $departure = Departure::query()->where('line_id', $line->id)->where('bus_id', $bus->id)->first();
    expect($departure)->not->toBeNull()
        ->and($departure->status)->toBe(DepartureStatus::Scheduled)
        ->and($departure->agency_id)->toBe($agency->id)
        ->and($departure->estimated_arrival_at)->not->toBeNull();

    // 08:00 + 1260 minutes (21h) = 05:00 next day
    expect($departure->estimated_arrival_at->format('H:i'))->toBe('05:00');
});

it('rejects creation when bus does not belong to the selected agency', function (): void {
    [$agency1, $line, $bus1, $model, $composition] = departuresReferences();

    $agency2 = Agency::query()->create([
        'code' => 'PAR',
        'name' => 'Agence Parakou',
        'city' => 'Parakou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    // bus1 belongs to agency1, but we pass agency2
    $this->actingAs(departuresManager())->post(route('departures.store'), [
        'line_uuid' => $line->uuid,
        'bus_uuid' => $bus1->uuid,
        'agency_uuid' => $agency2->uuid,
        'departure_date' => Carbon::today()->addDay()->format('Y-m-d'),
        'departure_time' => '08:00',
    ])->assertSessionHasErrors(['bus_uuid']);
});

it('updates a scheduled departure and blocks modification after departure', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->put(route('departures.update', $departure), [
            'departure_time' => '09:00',
        ])
        ->assertRedirect(route('departures.index'));

    $departure->refresh();
    expect($departure->departure_time)->toBe('09:00');

    // Now set status to departed and try to update — should fail
    $departure->update(['status' => DepartureStatus::Departed->value]);

    $this->actingAs(departuresManager())
        ->put(route('departures.update', $departure), [
            'departure_time' => '10:00',
        ])
        ->assertSessionHasErrors(['status']);
});

it('allows valid status transitions and blocks invalid ones', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    // scheduled → boarding (valid)
    $this->actingAs(departuresManager())
        ->patch(route('departures.status', $departure), ['status' => 'boarding'])
        ->assertRedirect();
    expect($departure->fresh()->status)->toBe(DepartureStatus::Boarding);

    // boarding → departed (valid)
    $this->actingAs(departuresManager())
        ->patch(route('departures.status', $departure->fresh()), ['status' => 'departed'])
        ->assertRedirect();
    expect($departure->fresh()->status)->toBe(DepartureStatus::Departed);

    // departed → arrived (valid)
    $this->actingAs(departuresManager())
        ->patch(route('departures.status', $departure->fresh()), ['status' => 'arrived'])
        ->assertRedirect();
    expect($departure->fresh()->status)->toBe(DepartureStatus::Arrived);

    // arrived → scheduled (invalid)
    $this->actingAs(departuresManager())
        ->patch(route('departures.status', $departure->fresh()), ['status' => 'scheduled'])
        ->assertSessionHasErrors(['status']);
});

it('cancels a scheduled departure', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->patch(route('departures.status', $departure), ['status' => 'cancelled'])
        ->assertRedirect();

    expect($departure->fresh()->status)->toBe(DepartureStatus::Cancelled);
});

it('prevents agency agents from creating departures', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $this->actingAs(agencyAgent())
        ->post(route('departures.store'), [
            'line_uuid' => $line->uuid,
            'bus_uuid' => $bus->uuid,
            'agency_uuid' => $agency->uuid,
            'departure_date' => Carbon::today()->addDay()->format('Y-m-d'),
            'departure_time' => '08:00',
        ])
        ->assertForbidden();
});

it('exposes stats for the departures ribbon', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id, 'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id, 'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'), 'departure_time' => '10:30',
        'status' => DepartureStatus::Boarding->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 2)
            ->where('stats.scheduled', 1)
            ->where('stats.boarding', 1),
        );
});

it('filters departures by exact date', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $today = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id, 'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id, 'agency_id' => $agency->id,
        'departure_date' => Carbon::tomorrow()->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['date' => Carbon::today()->format('Y-m-d')]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $today->uuid),
        );
});

it('exposes buses, drivers, and conductors for the departure form', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $this->actingAs(departuresManager())
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('buses', 1)
            ->where('buses.0.label', 'BJ-9254-RB')
            ->where('buses.0.uuid', $bus->uuid)
            ->has('drivers', 1)
            ->where('drivers.0.label', 'Jean Driver')
            ->where('drivers.0.uuid', $driver->uuid)
            ->has('conductors', 1)
            ->where('conductors.0.label', 'Marc Conductor')
            ->where('conductors.0.uuid', $conductor->uuid),
        );

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'driver_id' => $driver->id,
        'conductor_id' => $conductor->id,
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('buses', 1)
            ->has('drivers', 1)
            ->has('conductors', 1)
            ->where('departure.busUuid', $bus->uuid)
            ->where('departure.driverUuid', $driver->uuid)
            ->where('departure.conductorUuid', $conductor->uuid),
        );
});

it('never exposes internal numeric ids on departure ERP pages', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'),
        'departure_time' => '07:30',
        'driver_id' => $driver->id,
        'conductor_id' => $conductor->id,
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $manager = departuresManager();

    $this->actingAs($manager)
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('departures.data.0.lineId')
            ->missing('departures.data.0.busId')
            ->missing('departures.data.0.agencyId')
            ->missing('departures.data.0.driverId')
            ->missing('departures.data.0.conductorId')
            ->missing('lines.0.id')
            ->missing('agencies.0.id')
            ->missing('buses.0.id')
            ->missing('buses.0.agencyId')
            ->missing('drivers.0.id')
            ->missing('conductors.0.id')
            ->where('lines.0.uuid', $line->uuid)
            ->where('buses.0.uuid', $bus->uuid),
        );

    $this->actingAs($manager)
        ->get(route('departures.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('departure.lineId')
            ->missing('departure.busId')
            ->missing('departure.agencyId')
            ->missing('departure.driverId')
            ->missing('departure.conductorId')
            ->where('departure.lineUuid', $line->uuid)
            ->where('departure.busUuid', $bus->uuid)
            ->where('departure.agencyUuid', $agency->uuid),
        );
});

it('exposes a non-paginated collection via searchSellable for internal consumers like counter sale', function (): void {
    $line = Line::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'SELL-L1',
        'name' => 'Cotonou - Abidjan Sellable',
        'countries_crossed' => ['BJ', 'CI'],
        'distance_km' => 1020,
        'estimated_duration_minutes' => 1260,
        'status' => 'active',
    ]);

    foreach ([
        ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
        ['name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'stop_order' => 2, 'km_from_origin' => 1020],
    ] as $stop) {
        Stop::query()->create($stop + ['uuid' => (string) Str::uuid(), 'line_id' => $line->id, 'status' => 'active']);
    }

    $model = BusModel::query()->create([
        'uuid' => (string) Str::uuid(), 'brand' => 'KORA', 'name' => 'Coach 55', 'default_seat_count' => 55, 'status' => 'active',
    ]);
    $composition = BusComposition::query()->create([
        'uuid' => (string) Str::uuid(), 'code' => '2+2-55-SELL', 'name' => 'Standard', 'seat_count' => 55,
        'layout' => [['type' => 'standard', 'codes' => ['1A', '1B']]], 'status' => 'active',
    ]);
    $agency = Agency::query()->create(['code' => 'SEL', 'name' => 'Agence Vente', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(), 'registration_number' => 'SELL-001', 'bus_model_id' => $model->id,
        'composition_id' => $composition->id, 'agency_id' => $agency->id, 'brand' => 'KORA', 'model' => 'Coach 55',
        'seat_count' => 55, 'status' => 'active',
    ]);

    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id, 'agency_id' => $agency->id,
        'departure_date' => Carbon::today()->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $service = app(DepartureSearchService::class);

    $results = $service->searchSellable([
        'date' => Carbon::today()->format('Y-m-d'),
        'from' => 'Cotonou',
        'to' => 'Abidjan',
    ]);

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->count())->toBe(1);

    // search() keeps returning a paginator for the public API — the two methods must not collide.
    $paginated = $service->search([
        'date' => Carbon::today()->format('Y-m-d'),
        'from' => 'Cotonou',
        'to' => 'Abidjan',
    ]);

    expect($paginated)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginated->total())->toBe(1);
});
