<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Actions\ChangeDepartureStatusAction;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
});

function progUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/** @return array<string, string> */
function progHeaders(User $user): array
{
    // Le garde mémorise l'utilisateur de la requête précédente : sans cela, un 2e jeton (autre utilisateur)
    // dans le même test est rejeté (401). À chaque en-tête, on repart d'un garde vierge.
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDay())->plainTextToken, 'X-Terminal-Serial' => 'P501-PROG-01'];
}

function progDeparture(array $attributes = []): Departure
{
    $line = Line::query()->create([
        'code' => 'PG-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale', 'countries_crossed' => ['BJ'],
        'distance_km' => 40, 'estimated_duration_minutes' => 60, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'PG-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);

    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => 'scheduled',
        ...$attributes,
    ]);
}

/** @return array{agency: Agency, conductor: User, driver: User, director: User, admin: User} */
function progWorld(): array
{
    TerminalP501::query()->create(['serial_number' => 'P501-PROG-01']);
    $agency = Agency::factory()->create();

    return [
        'agency' => $agency,
        'conductor' => progUser('conductor', $agency),
        'driver' => progUser('bus_driver', $agency),
        'director' => progUser('operations_director', $agency),
        'admin' => progUser('system_admin'),
    ];
}

function progEventsOf(string $type): int
{
    return OpsNotificationEvent::query()->where('type', $type)->count();
}

it('moves a departure from scheduled to boarding, departed and arrived, recording the real times', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id]);
    $headers = progHeaders($w['conductor']);
    $departedAt = now()->subMinutes(2)->startOfSecond();

    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'boarding'])
        ->assertOk()->assertJsonPath('data.status', 'boarding');

    $response = $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed', 'occurred_at' => $departedAt->toISOString()])
        ->assertOk();

    expect($response->json('data.status'))->toBe('departed')
        ->and($response->json('data.departed_at'))->toBe($departedAt->copy()->utc()->toISOString())
        ->and($response->json('data.boarding_started_at'))->not->toBeNull()
        ->and($response->json('data.arrived_at'))->toBeNull()
        ->and(array_keys($response->json('data')))->not->toContain('id', 'bus_id', 'line_id');

    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'arrived'])
        ->assertOk()->assertJsonPath('data.status', 'arrived');

    $departure->refresh();
    expect($departure->status)->toBe(DepartureStatus::Arrived)
        ->and($departure->arrived_at)->not->toBeNull();
});

it('is the same status OPS Web reads: a departed bus stops selling and unlocks no-show closure', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id, 'status' => 'boarding']);

    $this->withHeaders(progHeaders($w['conductor']))->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed'])->assertOk();

    $fresh = Departure::query()->whereKey($departure->id)->firstOrFail();
    expect($fresh->status)->toBe(DepartureStatus::Departed)
        ->and(app(ResolveDepartureSellabilityAction::class)->handle($fresh)->isSellable)->toBeFalse();

    // Le web ne peut plus repasser ce départ en arrière : seule la transition « arrivé » reste permise.
    expect(fn () => app(ChangeDepartureStatusAction::class)->execute($fresh, DepartureStatus::Boarding))
        ->toThrow(ValidationException::class);

    $this->withHeaders(progHeaders($w['conductor']))->getJson("/api/v1/ops/departures/{$departure->uuid}")
        ->assertOk()->assertJsonPath('data.status', 'departed');
});

it('is idempotent: replaying the same status changes nothing and notifies once', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id, 'status' => 'boarding']);
    $headers = progHeaders($w['conductor']);

    $first = $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed', 'occurred_at' => now()->subMinutes(5)->toISOString()])->assertOk();
    $replay = $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed', 'occurred_at' => now()->toISOString()])->assertOk();

    expect($replay->json('data.departed_at'))->toBe($first->json('data.departed_at'))
        ->and(Activity::query()->where('log_name', 'departures')->where('event', 'status_changed')->count())->toBe(1)
        ->and(progEventsOf('departure_departed'))->toBe(1);
});

it('refuses impossible transitions with an actionable code', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id]);
    $headers = progHeaders($w['conductor']);

    // programmé → parti : l'embarquement doit avoir commencé.
    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_transition');
    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'arrived'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_transition');

    // Jamais de retour en arrière depuis le terminal.
    $departure->update(['status' => 'departed']);
    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'boarding'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_transition');

    expect($departure->refresh()->status)->toBe(DepartureStatus::Departed);
});

it('never lets the convoyeur cancel or invent a status', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id]);

    foreach (['cancelled', 'scheduled', 'unknown'] as $status) {
        $this->withHeaders(progHeaders($w['conductor']))->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => $status])->assertUnprocessable();
    }
    $this->withHeaders(progHeaders($w['conductor']))->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'boarding', 'occurred_at' => now()->addDay()->toISOString()])->assertUnprocessable();

    expect($departure->refresh()->status)->toBe(DepartureStatus::Scheduled);
});

it('is reserved to the assigned crew with the departures.progress permission', function (): void {
    $w = progWorld();
    $mine = progDeparture(['conductor_id' => $w['conductor']->id]);
    $theirs = progDeparture(['conductor_id' => progUser('conductor')->id]);
    $mechanic = progUser('mechanic', $w['agency']);
    $withMechanic = progDeparture(['mechanic_id' => $mechanic->id]);

    // Un départ qui n'est pas le sien.
    $this->withHeaders(progHeaders($w['conductor']))->postJson("/api/v1/ops/departures/{$theirs->uuid}/status", ['status' => 'boarding'])
        ->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');

    // Équipage affecté mais sans la permission (mécanicien).
    $this->withHeaders(progHeaders($mechanic))->postJson("/api/v1/ops/departures/{$withMechanic->uuid}/status", ['status' => 'boarding'])
        ->assertForbidden()->assertJsonPath('error.code', 'permission_denied');

    // Le chauffeur du même départ peut, lui, faire avancer.
    $mine->update(['driver_id' => $w['driver']->id]);
    $this->withHeaders(progHeaders($w['driver']))->postJson("/api/v1/ops/departures/{$mine->uuid}/status", ['status' => 'boarding'])->assertOk();

    expect($theirs->refresh()->status)->toBe(DepartureStatus::Scheduled)
        ->and($withMechanic->refresh()->status)->toBe(DepartureStatus::Scheduled);
});

it('publishes a single "bus parti" information to the crew and the agency ops directors, without e-mail', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id, 'driver_id' => $w['driver']->id, 'status' => 'boarding']);

    $this->withHeaders(progHeaders($w['conductor']))->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'departed'])->assertOk();

    $event = OpsNotificationEvent::query()->where('type', 'departure_departed')->sole();
    $notified = fn (User $who): int => OpsNotification::query()->where('user_id', $who->id)->where('event_id', $event->id)->count();

    expect($event->severity->value)->toBe('info')
        ->and($event->dedupe_key)->toBe('departure_departed:'.$departure->uuid)
        ->and($event->departure_id)->toBe($departure->id)
        ->and($notified($w['conductor']))->toBe(1)
        ->and($notified($w['driver']))->toBe(1)
        ->and($notified($w['director']))->toBe(1)
        ->and($notified($w['admin']))->toBe(0);

    Mail::assertNothingSent();
});

it('also notifies when OPS Web is the one who marks the bus departed', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id, 'status' => 'boarding']);

    app(ChangeDepartureStatusAction::class)->execute($departure, DepartureStatus::Departed);

    expect(progEventsOf('departure_departed'))->toBe(1);
});

it('does not notify for the other transitions', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id]);
    $headers = progHeaders($w['conductor']);

    $this->withHeaders($headers)->postJson("/api/v1/ops/departures/{$departure->uuid}/status", ['status' => 'boarding'])->assertOk();
    expect(progEventsOf('departure_departed'))->toBe(0);
});

it('records a delay reusing the OPS Web rules', function (): void {
    $w = progWorld();
    $departure = progDeparture(['conductor_id' => $w['conductor']->id]);
    $headers = progHeaders($w['conductor']);

    $this->withHeaders($headers)->patchJson("/api/v1/ops/departures/{$departure->uuid}/delay", ['delay_minutes' => 25, 'delay_reason' => 'Attente d\'un car de correspondance'])
        ->assertOk()
        ->assertJsonPath('data.delay_minutes', 25)
        ->assertJsonPath('data.delay_reason', 'Attente d\'un car de correspondance');

    expect(progEventsOf('departure_delayed'))->toBe(1);

    $onTime = progDeparture(['conductor_id' => $w['conductor']->id]);
    $this->withHeaders($headers)->patchJson("/api/v1/ops/departures/{$onTime->uuid}/delay", ['delay_minutes' => 0, 'delay_reason' => 'Départ à l’heure'])
        ->assertOk();
    expect(progEventsOf('departure_delayed'))->toBe(1);

    // Un départ déjà parti n'accepte plus de retard.
    $departure->update(['status' => 'departed']);
    $this->withHeaders($headers)->patchJson("/api/v1/ops/departures/{$departure->uuid}/delay", ['delay_minutes' => 30, 'delay_reason' => 'Trafic'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_delay');

    $other = progDeparture(['conductor_id' => progUser('conductor')->id]);
    $this->withHeaders($headers)->patchJson("/api/v1/ops/departures/{$other->uuid}/delay", ['delay_minutes' => 5, 'delay_reason' => 'Trafic'])
        ->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');
});
