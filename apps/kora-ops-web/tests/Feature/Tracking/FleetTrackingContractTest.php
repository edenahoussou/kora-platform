<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Tracking\Actions\ComputeRouteAdherenceAction;
use App\Modules\Tracking\Data\TrackingRouteAdherenceStatus;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function ftcUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/** @return array<string, string> */
function ftcHeaders(User $user, string $serial = 'P501-FTC-01'): array
{
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDay())->plainTextToken, 'X-Terminal-Serial' => $serial];
}

/**
 * @return array{agency: Agency, terminal: TerminalP501, conductor: User, departure: Departure, line: Line}
 */
function ftcWorld(): array
{
    $agency = Agency::factory()->create(['name' => 'Agence Test', 'code' => 'AG-TST']);
    $conductor = ftcUser('conductor', $agency);
    $conductor->forceFill(['phone' => '+22997000000'])->save();

    $terminal = TerminalP501::query()->create([
        'serial_number' => 'P501-FTC-01',
        'type' => 'p501',
        'agency_id' => $agency->id,
        'assigned_user_id' => $conductor->id,
    ]);

    $line = Line::query()->create([
        'code' => 'TR-'.Str::upper(Str::random(4)),
        'name' => 'Cotonou - Lomé Express',
        'type' => 'internationale',
        'countries_crossed' => ['BJ', 'TG'],
        'distance_km' => 160,
        'estimated_duration_minutes' => 180,
        'status' => 'active',
    ]);

    foreach ([
        ['Gare Centrale', 'Cotonou', 1, 0, 6.3654, 2.4183],
        ['Ouidah', 'Ouidah', 2, 40, 6.3631, 2.0851],
        ['Grand-Marché', 'Lomé', 3, 150, 6.1375, 1.2123],
    ] as [$name, $city, $order, $km, $lat, $lng]) {
        $physical = PhysicalStop::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'city' => $city,
            'country' => 'BJ',
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => 'active',
        ]);

        Stop::query()->create([
            'uuid' => (string) Str::uuid(),
            'line_id' => $line->id,
            'physical_stop_id' => $physical->id,
            'name' => $name,
            'city' => $city,
            'country' => 'BJ',
            'stop_order' => $order,
            'km_from_origin' => $km,
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => 'active',
        ]);
    }

    $bus = Bus::query()->create([
        'registration_number' => 'TR-FTC-01',
        'brand' => 'KORA',
        'model' => 'Coach VIP',
        'seat_count' => 54,
        'status' => 'active',
    ]);

    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-01',
        'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHours(3),
        'status' => 'departed',
        'conductor_id' => $conductor->id,
        'driver_id' => ftcUser('bus_driver')->id,
        'secondary_driver_id' => ftcUser('bus_driver')->id,
        'mechanic_id' => ftcUser('mechanic')->id,
    ]);

    return [
        'agency' => $agency,
        'terminal' => $terminal,
        'conductor' => $conductor,
        'departure' => $departure,
        'line' => $line,
    ];
}

/** @return array<int, array<string, mixed>> */
function ftcPositions(int $count, array $coords = [6.3654, 2.4183]): array
{
    return collect(range(1, $count))->map(fn (int $seq): array => [
        'position_uuid' => (string) Str::uuid(),
        'sequence' => $seq,
        'recorded_at' => now()->subSeconds(($count - $seq) * 10)->toISOString(),
        'latitude' => $coords[0] + ($seq * 0.0001),
        'longitude' => $coords[1] + ($seq * 0.0001),
        'accuracy_meters' => 8.5,
        'speed_kmh' => 60,
        'heading_degrees' => 120,
        'source' => 'gps',
        'status' => 'fix',
    ])->all();
}

it('serves the full fleet tracking contract consumed by the ops map', function (): void {
    $w = ftcWorld();

    Incident::query()->create([
        'departure_id' => $w['departure']->id,
        'bus_id' => $w['departure']->bus_id,
        'terminal_p501_id' => $w['terminal']->id,
        'created_by' => $w['conductor']->id,
        'type' => 'delay',
        'status' => 'open',
        'description' => 'Embouteillage à la sortie de Cotonou.',
        'latitude' => 6.37,
        'longitude' => 2.44,
        'occurred_at' => now()->subMinutes(15),
    ]);

    $this->withHeaders(ftcHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => ftcPositions(3),
    ])->assertOk();

    $director = ftcUser('operations_director');
    $response = $this->withHeaders(ftcHeaders($director))
        ->getJson('/api/v1/fleet/tracking')
        ->assertOk();

    $session = $response->json('data.0');

    expect($session['departure']['line_code'])->toBe($w['line']->code)
        ->and($session['departure']['line_name'])->toBe('Cotonou - Lomé Express')
        ->and($session['departure']['origin'])->toBe('Cotonou (Gare Centrale)')
        ->and($session['departure']['destination'])->toBe('Lomé (Grand-Marché)')
        ->and($session['bus']['model'])->toBe('KORA Coach VIP')
        ->and($session['bus']['seat_count'])->toBe(54)
        ->and($session['terminal']['serial_number'])->toBe('P501-FTC-01')
        ->and($session['conductor']['phone'])->toBe('+22997000000')
        ->and($session['position']['source'])->toBe('gps')
        ->and($session['agency']['code'])->toBe('AG-TST')
        ->and($session['recent_positions'])->toHaveCount(3)
        ->and($session['crew']['driver']['uuid'])->toBe($w['departure']->driver->uuid)
        ->and($session['crew']['mechanic']['uuid'])->toBe($w['departure']->mechanic->uuid)
        ->and($session['planned_stops'])->toHaveCount(3)
        ->and($session['planned_stops'][0]['order'])->toBe(1)
        ->and($session['planned_stops'][0]['km_from_origin'])->toBe(0)
        ->and($session['incidents'])->toHaveCount(1)
        ->and($session['incidents'][0]['type'])->toBe('delay')
        ->and($session['incidents'][0]['reporter_name'])->toBe($w['conductor']->name)
        ->and($session['route_adherence']['status'])->toBe(TrackingRouteAdherenceStatus::OnRoute->value)
        ->and($session['route_adherence']['distance_from_route_m'])->toBeInt();

    // Le contrat ne doit jamais exposer d'identifiant numérique interne.
    expect(json_encode($session))->not->toContain('"id":');
});

it('reports a deviation when the last position leaves the planned corridor', function (): void {
    $w = ftcWorld();

    // Position à ~30 km au nord de la polyligne Cotonou → Ouidah → Lomé.
    $this->withHeaders(ftcHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => ftcPositions(1, [6.65, 2.30]),
    ])->assertOk();

    $session = $this->withHeaders(ftcHeaders(ftcUser('operations_director')))
        ->getJson('/api/v1/fleet/tracking')
        ->assertOk()
        ->json('data.0');

    expect($session['route_adherence']['status'])->toBe(TrackingRouteAdherenceStatus::Deviation->value)
        ->and($session['route_adherence']['distance_from_route_m'])->toBeGreaterThan(1000);
});

it('computes route adherence statuses from the position and geolocated stops', function (): void {
    $action = app(ComputeRouteAdherenceAction::class);
    $stops = collect([
        new Stop(['latitude' => 6.3654, 'longitude' => 2.4183]),
        new Stop(['latitude' => 6.1375, 'longitude' => 1.2123]),
    ]);

    expect($action->execute(6.25, 1.82, $stops)['status'])->toBe('on_route')
        ->and($action->execute(7.0, 2.0, $stops)['status'])->toBe('deviation')
        ->and($action->execute(null, null, $stops)['status'])->toBe('insufficient_data')
        ->and($action->execute(6.25, 1.82, collect())['status'])->toBe('unavailable')
        ->and($action->execute(6.25, 1.82, collect([new Stop(['latitude' => 6.36, 'longitude' => 2.41])]))['status'])->toBe('unavailable');
});
