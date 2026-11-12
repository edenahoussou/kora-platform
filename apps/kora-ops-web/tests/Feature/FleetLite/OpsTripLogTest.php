<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseTripDetails;
use App\Modules\FleetLite\Mail\TripExpenseSubmittedMail;
use App\Modules\FleetLite\Models\DepartureOdometerReading;
use App\Modules\IdentityAccess\Http\Resources\AuthUserResource;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
    Storage::fake('private');
    config(['ops_session.force_terminal_for_all' => false]);
});

function tripUser(string|array $roles, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($roles);

    return $user;
}

/** @return array<string, string> */
function tripHeaders(User $user, ?string $serial = null): array
{
    Auth::forgetGuards();

    $headers = ['Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDay())->plainTextToken];
    if ($serial !== null) {
        $headers['X-Terminal-Serial'] = $serial;
    }

    return $headers;
}

/** @return array{agency: Agency, driver: User, conductor: User, director: User, admin: User, terminal: TerminalP501, departure: Departure, bus: Bus} */
function tripWorld(): array
{
    $agency = Agency::factory()->create();
    $driver = tripUser('bus_driver', $agency);
    $conductor = tripUser('conductor', $agency);
    $line = Line::query()->create([
        'code' => 'TL-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Lomé',
        'type' => 'internationale', 'countries_crossed' => ['BJ', 'TG'],
        'distance_km' => 160, 'estimated_duration_minutes' => 240, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'TL-'.Str::random(5), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);

    return [
        'agency' => $agency,
        'driver' => $driver,
        'conductor' => $conductor,
        'director' => tripUser('operations_director', $agency),
        'admin' => tripUser('system_admin'),
        'terminal' => TerminalP501::query()->create(['serial_number' => 'P501-TRIP-01']),
        'bus' => $bus,
        'departure' => Departure::query()->create([
            'line_id' => $line->id, 'bus_id' => $bus->id,
            'departure_date' => now()->toDateString(), 'departure_time' => '08:00',
            'estimated_arrival_at' => now()->addHours(4), 'status' => 'departed',
            'driver_id' => $driver->id, 'conductor_id' => $conductor->id,
        ]),
    ];
}

function fuelPayload(array $override = []): array
{
    return [
        'client_uuid' => (string) Str::uuid(),
        'kind' => 'fuel',
        'liters' => '65.5',
        'unit_price' => 680,
        'station_label' => 'Total Godomey',
        'odometer_km' => 182510,
        'payment_channel' => 'cash',
        'photo' => UploadedFile::fake()->image('ticket.jpg', 600, 900),
        ...$override,
    ];
}

it('exposes roles and the terminal requirement on the authenticated user', function (): void {
    $w = tripWorld();
    $both = tripUser(['bus_driver', 'conductor'], $w['agency']);
    $resource = fn (User $user): array => (new AuthUserResource($user))->resolve(Request::create('/'));

    expect($resource($w['driver'])['requires_terminal'])->toBeFalse()
        ->and($resource($w['driver'])['roles']->all())->toContain('bus_driver')
        ->and($resource($w['conductor'])['requires_terminal'])->toBeTrue()
        ->and($resource($both)['requires_terminal'])->toBeTrue();

    config(['ops_session.force_terminal_for_all' => true]);

    expect(app(OpsSessionGuard::class)->requiresTerminal($w['driver']))->toBeTrue();
});

it('lets a driver use crew routes without a P501 terminal', function (): void {
    $w = tripWorld();

    $this->withHeaders(tripHeaders($w['driver']))
        ->getJson("/api/v1/ops/departures/{$w['departure']->uuid}/trip-log")
        ->assertOk()
        ->assertJsonPath('data.departure_uuid', $w['departure']->uuid)
        ->assertJsonPath('data.odometer.start', null);

    $this->withHeaders(tripHeaders($w['driver']))
        ->getJson('/api/v1/ops/departures')
        ->assertOk();
});

it('still requires the terminal for a driver when OPS_FORCE_TERMINAL_FOR_ALL is true', function (): void {
    config(['ops_session.force_terminal_for_all' => true]);
    $w = tripWorld();

    $response = $this->withHeaders(tripHeaders($w['driver']))
        ->getJson("/api/v1/ops/departures/{$w['departure']->uuid}/trip-log");

    expect($response->status())->toBeGreaterThanOrEqual(400)
        ->and($response->json('error.code'))->toBe('terminal_unknown');
});

it('still requires the terminal for a conductor', function (): void {
    $w = tripWorld();

    $this->withHeaders(tripHeaders($w['conductor']))
        ->getJson("/api/v1/ops/departures/{$w['departure']->uuid}/trip-log")
        ->assertJsonPath('error.code', 'terminal_unknown');

    $this->withHeaders(tripHeaders($w['conductor'], 'P501-TRIP-01'))
        ->getJson("/api/v1/ops/departures/{$w['departure']->uuid}/trip-log")
        ->assertOk();
});

it('keeps terminal-only routes (boarding scan) behind the terminal for a driver', function (): void {
    $w = tripWorld();

    $this->withHeaders(tripHeaders($w['driver']))
        ->postJson('/api/v1/ops/boarding', [])
        ->assertJsonPath('error.code', 'terminal_unknown');
});

it('records start and end odometer readings and refuses an arrival below the departure', function (): void {
    $w = tripWorld();
    $url = "/api/v1/ops/departures/{$w['departure']->uuid}/odometer";

    $this->withHeaders(tripHeaders($w['driver']))
        ->putJson($url, ['kind' => 'start', 'value_km' => 182450])
        ->assertCreated()
        ->assertJsonPath('data.odometer.start.value_km', 182450);

    $this->withHeaders(tripHeaders($w['driver']))
        ->putJson($url, ['kind' => 'end', 'value_km' => 182400])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'odometer_inconsistent');

    $this->withHeaders(tripHeaders($w['conductor'], 'P501-TRIP-01'))
        ->putJson($url, ['kind' => 'end', 'value_km' => 182612])
        ->assertCreated()
        ->assertJsonPath('data.odometer.distance_km', 162);

    // Correction : écrase sans doublon.
    $this->withHeaders(tripHeaders($w['driver']))
        ->putJson($url, ['kind' => 'end', 'value_km' => 182615])
        ->assertOk()
        ->assertJsonPath('data.odometer.distance_km', 165);

    expect(DepartureOdometerReading::query()->count())->toBe(2)
        ->and(DepartureOdometerReading::query()->where('kind', 'end')->value('bus_id'))->toBe($w['bus']->id);
});

it('submits a fuel expense as pending, with details, photo and a detailed mail to ops with admin in copy', function (): void {
    $w = tripWorld();
    $payload = fuelPayload();

    $response = $this->withHeaders(tripHeaders($w['driver']))
        ->post("/api/v1/ops/departures/{$w['departure']->uuid}/trip-expenses", $payload, ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'fuel')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 44540)
        ->assertJsonPath('data.has_photo', true);

    $expense = Expense::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->category->code)->toBe('fuel')
        ->and($expense->agency_id)->toBe($w['agency']->id)
        ->and($expense->bus_id)->toBe($w['bus']->id)
        ->and($expense->departure_id)->toBe($w['departure']->id)
        ->and($expense->tripDetails->station_label)->toBe('Total Godomey')
        ->and((float) $expense->tripDetails->liters)->toBe(65.5)
        ->and($expense->getMedia('evidence'))->toHaveCount(1);

    Mail::assertQueued(TripExpenseSubmittedMail::class, function (TripExpenseSubmittedMail $mail) use ($w): bool {
        return $mail->hasTo($w['director']->email)
            && $mail->hasCc($w['admin']->email)
            && ! $mail->hasTo($w['admin']->email);
    });

    $rendered = (new TripExpenseSubmittedMail($expense->id))->render();
    expect($rendered)->toContain('Total Godomey')
        ->and($rendered)->toContain('65,50')
        ->and($rendered)->toContain('44 540');
});

it('is idempotent by client uuid and refuses a uuid reused by another user', function (): void {
    $w = tripWorld();
    $payload = fuelPayload();
    $url = "/api/v1/ops/departures/{$w['departure']->uuid}/trip-expenses";

    $this->withHeaders(tripHeaders($w['driver']))->post($url, $payload, ['Accept' => 'application/json'])->assertCreated();
    $this->withHeaders(tripHeaders($w['driver']))->post($url, fuelPayload(['client_uuid' => $payload['client_uuid']]), ['Accept' => 'application/json'])->assertOk();

    $this->withHeaders(tripHeaders($w['conductor'], 'P501-TRIP-01'))
        ->post($url, fuelPayload(['client_uuid' => $payload['client_uuid']]), ['Accept' => 'application/json'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'trip_entry_uuid_conflict');

    expect(ExpenseTripDetails::query()->count())->toBe(1);
    Mail::assertQueued(TripExpenseSubmittedMail::class, 1);
});

it('requires the ticket photo for fuel and the amount and post for tolls and border taxes', function (): void {
    $w = tripWorld();
    $url = "/api/v1/ops/departures/{$w['departure']->uuid}/trip-expenses";

    $this->withHeaders(tripHeaders($w['driver']))
        ->post($url, fuelPayload(['photo' => null]), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');

    $this->withHeaders(tripHeaders($w['driver']))
        ->postJson($url, ['client_uuid' => (string) Str::uuid(), 'kind' => 'toll'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'checkpoint_label']);

    $this->withHeaders(tripHeaders($w['driver']))
        ->postJson($url, [
            'client_uuid' => (string) Str::uuid(),
            'kind' => 'border_tax',
            'amount' => 5000,
            'checkpoint_label' => 'Hillacondji',
            'country_code' => 'tg',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'border_tax')
        ->assertJsonPath('data.country_code', 'TG');

    expect(Expense::query()->whereHas('category', fn ($q) => $q->where('code', 'border_taxes'))->count())->toBe(1);
});

it('refuses trip log entries on a departure the user is not assigned to', function (): void {
    $w = tripWorld();
    $stranger = tripUser('bus_driver', $w['agency']);

    $this->withHeaders(tripHeaders($stranger))
        ->putJson("/api/v1/ops/departures/{$w['departure']->uuid}/odometer", ['kind' => 'start', 'value_km' => 1000])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'departure_not_assigned');
});

it('shows the trip log on the bus page and lets the office approve the expense', function (): void {
    $w = tripWorld();
    $this->withHeaders(tripHeaders($w['driver']))
        ->putJson("/api/v1/ops/departures/{$w['departure']->uuid}/odometer", ['kind' => 'start', 'value_km' => 1000])
        ->assertCreated();
    $this->withHeaders(tripHeaders($w['driver']))
        ->putJson("/api/v1/ops/departures/{$w['departure']->uuid}/odometer", ['kind' => 'end', 'value_km' => 1160])
        ->assertCreated();
    $this->withHeaders(tripHeaders($w['driver']))
        ->post("/api/v1/ops/departures/{$w['departure']->uuid}/trip-expenses", fuelPayload(), ['Accept' => 'application/json'])
        ->assertCreated();

    Auth::forgetGuards();

    $this->actingAs($w['admin'])
        ->get("/fleet/{$w['bus']->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tripLog.mileage.stats.currentKm', 1160)
            ->where('tripLog.mileage.stats.totalDistanceKm', 160)
            ->where('tripLog.fuel.items.0.station', 'Total Godomey')
            ->where('tripLog.fuel.items.0.amount', 44540));

    $expense = Expense::query()->firstOrFail();

    $this->actingAs($w['admin'])
        ->post("/expenses/{$expense->uuid}/approve")
        ->assertRedirect();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Approved);
});
