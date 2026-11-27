<?php

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
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
        $physical = PhysicalStop::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $stop['name'].' DM',
            'city' => $stop['city'],
            'country' => $stop['country'],
            'status' => 'active',
        ]);
        Stop::query()->create($stop + ['uuid' => (string) Str::uuid(), 'line_id' => $line->id, 'physical_stop_id' => $physical->id, 'status' => 'active']);
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

it('lists departures with line, bus, and crew without exposing numeric ids', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'estimated_arrival_at' => Carbon::today(RevenuePeriod::TIMEZONE)->setTime(7, 30)->addMinutes(1260),
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
            ->where('departures.data.0.driverName', 'Jean Driver')
            ->where('departures.data.0.conductorName', 'Marc Conductor')
            ->where('departures.data.0.status', 'scheduled')
            ->missing('departures.data.0.id')
            ->where('permissions.canCreate', true),
        );
});

it('filters departures by status and search term', function (): void {
    [$agency1, $line, $bus1, $model, $composition] = departuresReferences();

    $bus2 = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-2222-RB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'brand' => 'KORA',
        'model' => 'Coach 55',
        'seat_count' => 55,
        'status' => 'active',
    ]);

    $departure1 = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus1->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $departure2 = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus2->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
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

    // Filter by search term (bus registration)
    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['q' => '2222']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $departure2->uuid),
        );
});

it('shows a departure detail with line stops, bus, and crew', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'estimated_arrival_at' => Carbon::today(RevenuePeriod::TIMEZONE)->setTime(7, 30)->addMinutes(1260),
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
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->addDay()->format('Y-m-d'),
        'departure_time' => '08:00',
        'driver_uuid' => null,
        'conductor_uuid' => null,
    ]);

    $response->assertRedirect(route('departures.index'));

    $departure = Departure::query()->where('line_id', $line->id)->where('bus_id', $bus->id)->first();
    expect($departure)->not->toBeNull()
        ->and($departure->status)->toBe(DepartureStatus::Scheduled)
        ->and($departure->estimated_arrival_at)->not->toBeNull();

    // 08:00 locale (Porto-Novo, UTC+1) = 07:00 UTC ; +1260 min (21h)
    // = 04:00 UTC le lendemain, soit 05:00 en heure locale.
    expect($departure->scheduledDepartureAt()->utc()->format('H:i'))->toBe('07:00')
        ->and($departure->estimated_arrival_at->setTimezone('Africa/Porto-Novo')->format('H:i'))->toBe('05:00');
});

it('updates a scheduled departure and blocks modification after departure', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->put(route('departures.update', $departure), [
            'departure_time' => '09:00',
        ])
        ->assertRedirect(route('departures.index'));

    $departure->refresh();
    expect(substr($departure->departure_time, 0, 5))->toBe('09:00');

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
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
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
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
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
            'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->addDay()->format('Y-m-d'),
            'departure_time' => '08:00',
        ])
        ->assertForbidden();
});

it('exposes stats for the departures ribbon', function (): void {
    [$agency, $line, $bus] = departuresReferences();

    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'), 'departure_time' => '10:30',
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
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => Carbon::tomorrow(RevenuePeriod::TIMEZONE)->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.index', ['date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d')]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $today->uuid),
        );
});

it('exposes crew and passenger manifest data on the departure detail', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'driver_id' => $driver->id,
        'conductor_id' => $conductor->id,
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $this->actingAs(departuresManager())
        ->get(route('departures.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('passengers')
            ->has('boardingSummary')
            ->has('timeline')
            ->where('departure.busUuid', $bus->uuid)
            ->where('departure.driverUuid', $driver->uuid)
            ->where('departure.conductorUuid', $conductor->uuid)
            ->where('departure.driverName', 'Jean Driver')
            ->where('departure.conductorName', 'Marc Conductor')
            ->where('boardingSummary.total', 0),
        );
});

it('never exposes internal numeric ids on departure ERP pages', function (): void {
    [$agency, $line, $bus, $model, $composition, $driver, $conductor] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
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
            ->missing('departures.data.0.driverId')
            ->missing('departures.data.0.conductorId')
            ->missing('lines.0.id')
            ->where('lines.0.uuid', $line->uuid),
        );

    $this->actingAs($manager)
        ->get(route('departures.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('departure.lineId')
            ->missing('departure.busId')
            ->missing('departure.driverId')
            ->missing('departure.conductorId')
            ->where('departure.lineUuid', $line->uuid)
            ->where('departure.busUuid', $bus->uuid),
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
        $physical = PhysicalStop::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $stop['name'].' DM2',
            'city' => $stop['city'],
            'country' => $stop['country'],
            'status' => 'active',
        ]);
        Stop::query()->create($stop + ['uuid' => (string) Str::uuid(), 'line_id' => $line->id, 'physical_stop_id' => $physical->id, 'status' => 'active']);
    }

    $model = BusModel::query()->create([
        'uuid' => (string) Str::uuid(), 'brand' => 'KORA', 'name' => 'Coach 55', 'default_seat_count' => 55, 'status' => 'active',
    ]);
    $composition = BusComposition::query()->create([
        'uuid' => (string) Str::uuid(), 'code' => '2+2-55-SELL', 'name' => 'Standard', 'seat_count' => 55,
        'layout' => [['type' => 'standard', 'codes' => ['1A', '1B']]], 'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(), 'registration_number' => 'SELL-001', 'bus_model_id' => $model->id,
        'composition_id' => $composition->id, 'brand' => 'KORA', 'model' => 'Coach 55',
        'seat_count' => 55, 'status' => 'active',
    ]);

    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'), 'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $service = app(DepartureSearchService::class);

    $results = $service->searchSellable([
        'date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'from' => 'Cotonou',
        'to' => 'Abidjan',
    ]);

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->count())->toBe(1);

    // search() keeps returning a paginator for the public API — the two methods must not collide.
    $paginated = $service->search([
        'date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'from' => 'Cotonou',
        'to' => 'Abidjan',
    ]);

    expect($paginated)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginated->total())->toBe(1);
});

it('boards only today by default and filters by origin city and physical stop', function (): void {
    [$agency, $line, $bus, $model, $composition] = departuresReferences();

    $today = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->addDay()->format('Y-m-d'),
        'departure_time' => '10:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $manager = departuresManager();

    $this->actingAs($manager)
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.total', 1)
            ->where('departures.data.0.uuid', $today->uuid)
            ->where('departures.data.0.originCity', 'Cotonou')
            ->where('departures.data.0.destinationCity', 'Abidjan')
            ->where('departures.data.0.originPoint', 'Cotonou DM')
            ->where('appliedDate', Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d')),
        );

    $this->actingAs($manager)
        ->get(route('departures.index', ['city' => 'Cotonou']))
        ->assertInertia(fn (Assert $page) => $page->where('departures.total', 1));

    $this->actingAs($manager)
        ->get(route('departures.index', ['city' => 'Bohicon']))
        ->assertInertia(fn (Assert $page) => $page->where('departures.total', 0));

    $originPoint = PhysicalStop::query()->where('city', 'Cotonou')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('departures.index', ['physical_stop' => $originPoint->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('departures.total', 1));
});

it('computes the fill rate per leg, not reservations over seats', function (): void {
    [$agency, $line, $bus, $model, $composition] = departuresReferences();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    $makeSeat = fn (int $number): Seat => Seat::query()->create([
        'uuid' => (string) Str::uuid(),
        'bus_id' => $bus->id,
        'code' => '1A-'.$number,
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    [$cotonou, $portoNovo, $abidjan] = $line->stops()->orderBy('stop_order')->get()->all();

    $bookSegment = function (Seat $seat, Stop $origin, Stop $destination, string $status) use ($departure): void {
        $reservation = Reservation::query()->create([
            'status' => 'ticketed',
            'total_amount' => 5000,
            'currency' => 'XOF',
            'channel' => ReservationChannel::Counter,
        ]);

        ReservationSegment::query()->create([
            'reservation_id' => $reservation->id,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => $status,
        ]);
    };

    // 20 passagers sur le premier tronçon et 10 sur le second : la charge
    // maximale est 20/55, pas le total de 30 réservations.
    for ($i = 0; $i < 20; $i++) {
        $bookSegment($makeSeat($i + 1), $cotonou, $portoNovo, 'ticketed');
    }
    for ($i = 0; $i < 10; $i++) {
        $bookSegment($makeSeat($i + 21), $portoNovo, $abidjan, 'ticketed');
    }
    // Un segment libéré ne doit plus compter.
    $bookSegment($makeSeat(31), $cotonou, $abidjan, 'released');

    $this->actingAs(departuresManager())
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.data.0.reservationsCount', 30)
            ->where('departures.data.0.fillRate', 36)
            ->where('departures.data.0.averageFillRate', 27)
            ->where('departures.data.0.peakLegLabel', 'Cotonou DM → Porto-Novo DM')
            ->where('stats.seatsSold', 30)
            ->where('stats.fillRate', 27.3),
        );
});

it('releases segments from the departure detail via web no-show and early-exit', function (): void {
    [$agency, $line, $bus, $model, $composition] = departuresReferences();
    [$cotonou, $portoNovo, $abidjan] = $line->stops()->orderBy('stop_order')->get()->all();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Departed->value,
    ]);

    $makePassenger = function (Seat $seat, Stop $origin, Stop $destination, string $segmentStatus) use ($departure): array {
        $reservation = Reservation::query()->create([
            'status' => 'ticketed',
            'total_amount' => 5000,
            'currency' => 'XOF',
            'channel' => ReservationChannel::Counter,
        ]);
        $passenger = ReservationPassenger::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'first_name' => 'Fatou',
            'last_name' => 'Diop',
        ]);
        Payment::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        $segment = ReservationSegment::query()->create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'leg_order' => 1,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
        ]);
        $ticket = Ticket::query()->create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'qr_code' => 'QR-'.Str::random(24),
            'short_code' => Str::upper(Str::random(6)),
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return [$ticket, $segment];
    };

    $seatA = Seat::query()->create(['uuid' => (string) Str::uuid(), 'bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $seatB = Seat::query()->create(['uuid' => (string) Str::uuid(), 'bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    [$noShowTicket, $noShowSegment] = $makePassenger($seatA, $cotonou, $abidjan, 'ticketed');
    [$exitTicket, $exitSegment] = $makePassenger($seatB, $cotonou, $abidjan, 'boarded');

    $manager = departuresManager();

    // No-show : billet émis + départ parti → tout le trajet est libéré.
    $this->actingAs($manager)
        ->post(route('departures.no-show', $departure), ['ticket_uuid' => $noShowTicket->uuid])
        ->assertRedirect();

    expect((string) $noShowSegment->refresh()->getRawOriginal('status'))->toBe('released')
        ->and(SeatReleaseEvent::query()
            ->where('ticket_id', $noShowTicket->id)
            ->where('departure_id', $departure->id)
            ->whereNull('terminal_p501_id')
            ->where('status', 'accepted')
            ->exists())->toBeTrue();

    // Descente anticipée : segment embarqué → tronçons non parcourus
    // libérés à partir de l'arrêt réel choisi.
    $this->actingAs($manager)
        ->post(route('departures.early-exit', $departure), [
            'ticket_uuid' => $exitTicket->uuid,
            'stop_uuid' => $portoNovo->uuid,
        ])
        ->assertRedirect();

    expect((string) $exitSegment->refresh()->getRawOriginal('status'))->toBe('released');

    // Un agent guichet (departures.view, pas manage) ne peut pas libérer.
    $this->actingAs(agencyAgent())
        ->post(route('departures.no-show', $departure), ['ticket_uuid' => $noShowTicket->uuid])
        ->assertForbidden();
});

it('closes no-shows in bulk and reverses a boarding from the departure detail', function (): void {
    [$agency, $line, $bus, $model, $composition] = departuresReferences();
    [$cotonou, $portoNovo, $abidjan] = $line->stops()->orderBy('stop_order')->get()->all();

    $departure = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Departed->value,
    ]);

    $makePassenger = function (Seat $seat, Stop $origin, Stop $destination, string $segmentStatus) use ($departure): array {
        $reservation = Reservation::query()->create([
            'status' => 'ticketed',
            'total_amount' => 5000,
            'currency' => 'XOF',
            'channel' => ReservationChannel::Counter,
        ]);
        $passenger = ReservationPassenger::query()->create([
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
        ]);
        $segment = ReservationSegment::query()->create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'leg_order' => 1,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
        ]);
        $ticket = Ticket::query()->create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'qr_code' => 'QR-'.Str::random(24),
            'short_code' => Str::upper(Str::random(6)),
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return [$ticket, $segment];
    };

    $seatA = Seat::query()->create(['uuid' => (string) Str::uuid(), 'bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $seatB = Seat::query()->create(['uuid' => (string) Str::uuid(), 'bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    [$absentTicket, $absentSegment] = $makePassenger($seatA, $cotonou, $abidjan, 'ticketed');
    [$boardedTicket, $boardedSegment] = $makePassenger($seatB, $cotonou, $abidjan, 'boarded');

    $scan = BoardingEvent::query()->create([
        'ticket_id' => $boardedTicket->id,
        'departure_id' => $departure->id,
        'operation_uuid' => (string) Str::uuid(),
        'local_recorded_at' => now(),
        'server_recorded_at' => now(),
        'status' => 'accepted',
    ]);

    $manager = departuresManager();

    // Clôture en masse : le billet jamais scanné devient no-show.
    $this->actingAs($manager)
        ->post(route('departures.close-no-shows', $departure))
        ->assertRedirect();

    expect((string) $absentSegment->refresh()->getRawOriginal('status'))->toBe('released')
        ->and(SeatReleaseEvent::query()
            ->where('ticket_id', $absentTicket->id)
            ->where('departure_id', $departure->id)
            ->whereNull('terminal_p501_id')
            ->where('status', 'accepted')
            ->exists())->toBeTrue()
        // Le passager embarqué n'est pas touché par la clôture.
        ->and((string) $boardedSegment->refresh()->getRawOriginal('status'))->toBe('boarded');

    // Annulation d'embarquement : motif obligatoire, segment repasse
    // à « ticketed », l'événement d'origine reste inchangé.
    $this->actingAs($manager)
        ->post(route('departures.reverse-boarding', $departure), [
            'ticket_uuid' => $boardedTicket->uuid,
            'reason' => 'Non',
        ])
        ->assertSessionHasErrors('reason');

    $this->actingAs($manager)
        ->post(route('departures.reverse-boarding', $departure), [
            'ticket_uuid' => $boardedTicket->uuid,
            'reason' => 'Scan erroné, passager non présent à bord.',
        ])
        ->assertRedirect();

    expect((string) $boardedSegment->refresh()->getRawOriginal('status'))->toBe('ticketed')
        ->and($scan->refresh()->status->value ?? (string) $scan->getRawOriginal('status'))->toBe('accepted');

    // Sans la permission dédiée boarding.reverse : refusé.
    $this->actingAs(agencyAgent())
        ->post(route('departures.reverse-boarding', $departure), [
            'ticket_uuid' => $boardedTicket->uuid,
            'reason' => 'Scan erroné, passager non présent à bord.',
        ])
        ->assertForbidden();
});

it('displays the real circulation direction for return departures', function (): void {
    [$agency, $line, $bus, $model, $composition] = departuresReferences();
    [$cotonou, $portoNovo, $abidjan] = $line->stops()->orderBy('stop_order')->get()->all();

    // Départ aller sans stop_times : l'ordre canonique de la ligne prévaut.
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '07:30',
        'status' => DepartureStatus::Scheduled->value,
    ]);

    // Départ retour publié : stop_times stocke l'ordre de circulation réel,
    // inversé par rapport à la définition de la ligne.
    $return = Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => Carbon::today(RevenuePeriod::TIMEZONE)->format('Y-m-d'),
        'departure_time' => '16:30',
        'departure_timezone' => 'Africa/Porto-Novo',
        'status' => DepartureStatus::Scheduled->value,
        'stop_times' => [
            ['stop_uuid' => $abidjan->uuid, 'timezone' => 'Africa/Porto-Novo', 'departure_minutes' => 0, 'arrival_minutes' => null],
            ['stop_uuid' => $portoNovo->uuid, 'timezone' => 'Africa/Porto-Novo', 'departure_minutes' => 1245, 'arrival_minutes' => 1235],
            ['stop_uuid' => $cotonou->uuid, 'timezone' => 'Africa/Porto-Novo', 'departure_minutes' => null, 'arrival_minutes' => 1260],
        ],
    ]);

    $seat = Seat::query()->create([
        'uuid' => (string) Str::uuid(),
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $reservation = Reservation::query()->create([
        'status' => 'ticketed',
        'total_amount' => 5000,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Counter,
    ]);

    // Les *_stop_order des segments sont des positions dans stop_times.
    ReservationSegment::query()->create([
        'reservation_id' => $reservation->id,
        'departure_id' => $return->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $abidjan->id,
        'destination_stop_id' => $portoNovo->id,
        'origin_stop_order' => 0,
        'destination_stop_order' => 1,
        'status' => 'ticketed',
    ]);

    $manager = departuresManager();

    $this->actingAs($manager)
        ->get(route('departures.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departures.data.0.originCity', 'Cotonou')
            ->where('departures.data.0.destinationCity', 'Abidjan')
            ->where('departures.data.0.isReturn', false)
            ->where('departures.data.1.uuid', $return->uuid)
            ->where('departures.data.1.originCity', 'Abidjan')
            ->where('departures.data.1.destinationCity', 'Cotonou')
            ->where('departures.data.1.originPoint', 'Abidjan DM')
            ->where('departures.data.1.isReturn', true)
            ->where('departures.data.1.peakLegLabel', 'Abidjan DM → Porto-Novo DM'),
        );

    $this->actingAs($manager)
        ->get(route('departures.show', $return))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('departure.originCity', 'Abidjan')
            ->where('departure.destinationCity', 'Cotonou')
            ->where('departure.originPoint', 'Abidjan DM')
            ->where('departure.isReturn', true)
            ->where('departure.stops.0.uuid', $abidjan->uuid)
            ->where('departure.stops.0.circulationOrder', 0)
            ->where('departure.stops.0.departureTime', '16:30')
            ->where('departure.stops.0.arrivalTime', null)
            ->where('departure.stops.1.uuid', $portoNovo->uuid)
            ->where('departure.stops.1.circulationOrder', 1)
            ->where('departure.stops.1.arrivalTime', '13:05')
            ->where('departure.stops.1.departureTime', '13:15')
            ->where('departure.stops.2.uuid', $cotonou->uuid)
            ->where('departure.stops.2.circulationOrder', 2)
            ->where('departure.stops.2.arrivalTime', '13:30')
            ->where('departure.stops.2.departureTime', null),
        );
});
