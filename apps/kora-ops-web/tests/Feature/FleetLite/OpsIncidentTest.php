<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\IncidentStatus;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
});

function incUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/** @return array<string, string> */
function incHeaders(User $user, string $serial = 'P501-INC-01'): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDay())->plainTextToken, 'X-Terminal-Serial' => $serial];
}

function incDeparture(array $crew = []): Departure
{
    $line = Line::query()->create([
        'code' => 'IN-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale', 'countries_crossed' => ['BJ'],
        'distance_km' => 40, 'estimated_duration_minutes' => 60, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'IN-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);

    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => 'boarding',
        ...$crew,
    ]);
}

function incPayload(Departure $departure, array $override = []): array
{
    return [
        'incident_uuid' => (string) Str::uuid(),
        'type' => 'breakdown',
        'description' => 'Crevaison à Ouidah, changement de roue en cours.',
        'departure_uuid' => $departure->uuid,
        ...$override,
    ];
}

/** @return array{agency: Agency, terminal: TerminalP501, conductor: User, driver: User, director: User, otherDirector: User, admin: User, departure: Departure} */
function incWorld(): array
{
    $agency = Agency::factory()->create();
    $conductor = incUser('conductor', $agency);
    $driver = incUser('bus_driver', $agency);

    return [
        'agency' => $agency,
        'terminal' => TerminalP501::query()->create(['serial_number' => 'P501-INC-01']),
        'conductor' => $conductor,
        'driver' => $driver,
        'director' => incUser('operations_director', $agency),
        'otherDirector' => incUser('operations_director', Agency::factory()->create()),
        'admin' => incUser('system_admin'),
        'departure' => incDeparture(['conductor_id' => $conductor->id, 'driver_id' => $driver->id]),
    ];
}

it('records an incident with uuids and codes only, and links departure, bus and terminal', function (): void {
    $w = incWorld();
    $terminal = $w['terminal'];
    $payload = incPayload($w['departure'], ['latitude' => 6.3654, 'longitude' => 2.4183, 'occurred_at' => now()->subMinutes(3)->toISOString()]);

    $response = $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $payload)->assertCreated();

    $incident = Incident::query()->where('uuid', $payload['incident_uuid'])->firstOrFail();

    expect($response->json('data.uuid'))->toBe($payload['incident_uuid'])
        ->and($response->json('data.type'))->toBe('breakdown')
        ->and($response->json('data.status'))->toBe('open')
        ->and($response->json('data.departure.uuid'))->toBe($w['departure']->uuid)
        ->and($response->json('data.departure.line_code'))->toBe($w['departure']->line->code)
        ->and(array_keys($response->json('data')))->not->toContain('id', 'bus_id', 'created_by', 'departure_id')
        ->and($incident->created_by)->toBe($w['conductor']->id)
        ->and($incident->bus_id)->toBe($w['departure']->bus_id)
        ->and($incident->departure_id)->toBe($w['departure']->id)
        ->and($incident->terminal_p501_id)->toBe($terminal->id)
        ->and($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->occurred_at)->not->toBeNull()
        ->and(Activity::query()->where('log_name', 'incidents')->where('event', 'reported')->count())->toBe(1);
});

it('is idempotent: a replay returns the existing incident without duplicating it or its alert', function (): void {
    $w = incWorld();
    $payload = incPayload($w['departure']);

    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $payload)->assertCreated();
    $replay = $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $payload)->assertOk();

    expect($replay->json('data.uuid'))->toBe($payload['incident_uuid'])
        ->and(Incident::query()->count())->toBe(1)
        ->and(OpsNotificationEvent::query()->where('type', 'incident_reported')->count())->toBe(1);

    Mail::assertSent(OpsAlertMail::class, 1);
});

it('refuses an incident uuid already used by another user', function (): void {
    $w = incWorld();
    $payload = incPayload($w['departure']);
    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $payload)->assertCreated();

    $this->withHeaders(incHeaders($w['driver']))->postJson('/api/v1/ops/incidents', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'incident_uuid_conflict');

    expect(Incident::query()->count())->toBe(1);
});

it('answers unknown and not-assigned departures identically, so another departure is never revealed', function (): void {
    $w = incWorld();
    $foreign = incDeparture(['conductor_id' => incUser('conductor')->id]);

    $notAssigned = $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($foreign))
        ->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');
    $unknown = $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($foreign, ['departure_uuid' => (string) Str::uuid()]))
        ->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');

    expect($notAssigned->json('error.message'))->toBe($unknown->json('error.message'))
        ->and(Incident::query()->count())->toBe(0);

    // Un départ annulé n'est plus accessible à l'équipage.
    $cancelled = incDeparture(['conductor_id' => $w['conductor']->id, 'status' => 'cancelled']);
    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($cancelled))
        ->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');
});

it('requires the incidents.report permission', function (): void {
    $w = incWorld();
    Role::findByName('conductor')->revokePermissionTo('incidents.report');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($w['departure']))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'permission_denied');

    expect(Incident::query()->count())->toBe(0);
});

it('validates the payload', function (): void {
    $w = incWorld();
    $headers = incHeaders($w['conductor']);

    foreach ([
        ['description' => 'abc'],
        ['description' => str_repeat('x', 1001)],
        ['type' => 'unknown'],
        ['incident_uuid' => 'not-a-uuid'],
        ['latitude' => 6.36],
        ['latitude' => 120, 'longitude' => 2.4],
        ['occurred_at' => now()->addDay()->toISOString()],
        ['occurred_at' => now()->subDays(30)->toISOString()],
    ] as $invalid) {
        $this->withHeaders($headers)->postJson('/api/v1/ops/incidents', incPayload($w['departure'], $invalid))->assertUnprocessable();
    }

    expect(Incident::query()->count())->toBe(0);
});

it('warns the crew, the agency ops director and the superadmins, with an e-mail and an audit log', function (): void {
    $w = incWorld();
    $payload = incPayload($w['departure']);

    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $payload)->assertCreated();

    $event = OpsNotificationEvent::query()->where('type', 'incident_reported')->sole();
    $notified = fn (User $who): int => OpsNotification::query()->where('user_id', $who->id)->where('event_id', $event->id)->count();

    expect($event->severity->value)->toBe('warning')
        ->and($event->dedupe_key)->toBe('incident_reported:'.$payload['incident_uuid'])
        ->and($event->data)->toBe(['incident_uuid' => $payload['incident_uuid'], 'incident_type' => 'breakdown'])
        ->and($notified($w['conductor']))->toBe(1)
        ->and($notified($w['driver']))->toBe(1)
        ->and($notified($w['director']))->toBe(1)
        ->and($notified($w['admin']))->toBe(1)
        ->and($notified($w['otherDirector']))->toBe(0);

    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo($w['director']->email)
        && $mail->hasCc($w['admin']->email)
        && ! $mail->hasTo($w['otherDirector']->email));

    // Audit : une ligne par destinataire dans notification_logs.
    $logs = NotificationLog::query()->where('reference_id', $event->id)->get()->keyBy('recipient');
    expect($logs)->toHaveCount(2)
        ->and($logs[$w['director']->email]->status)->toBe('sent')
        ->and($logs[$w['director']->email]->payload['mail_role'])->toBe('to')
        ->and($logs[$w['admin']->email]->payload['mail_role'])->toBe('cc');
});

it('turns accidents and security incidents into critical, non-disableable alerts', function (): void {
    $w = incWorld();

    foreach (['accident', 'security'] as $type) {
        $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($w['departure'], ['type' => $type]))->assertCreated();
    }
    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', incPayload($w['departure'], ['type' => 'delay']))->assertCreated();

    expect(OpsNotificationEvent::query()->where('type', 'incident_critical')->count())->toBe(2)
        ->and(OpsNotificationEvent::query()->where('type', 'incident_critical')->first()->severity->value)->toBe('critical')
        ->and(OpsNotificationEvent::query()->where('type', 'incident_reported')->count())->toBe(1);
});

it('lets a convoyeur list and read only his own incidents', function (): void {
    $w = incWorld();
    $mine = incPayload($w['departure']);
    $this->withHeaders(incHeaders($w['conductor']))->postJson('/api/v1/ops/incidents', $mine)->assertCreated();
    $this->withHeaders(incHeaders($w['driver']))->postJson('/api/v1/ops/incidents', incPayload($w['departure'], ['type' => 'delay']))->assertCreated();

    $list = $this->withHeaders(incHeaders($w['conductor']))->getJson('/api/v1/ops/incidents')->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.uuid'))->toBe($mine['incident_uuid'])
        ->and($list->json('meta.total'))->toBe(1);

    $this->withHeaders(incHeaders($w['conductor']))->getJson('/api/v1/ops/incidents?status=resolved')->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders(incHeaders($w['conductor']))->getJson('/api/v1/ops/incidents?departure_uuid='.$w['departure']->uuid)->assertOk()->assertJsonCount(1, 'data');

    $this->withHeaders(incHeaders($w['conductor']))->getJson('/api/v1/ops/incidents/'.$mine['incident_uuid'])
        ->assertOk()->assertJsonPath('data.uuid', $mine['incident_uuid']);

    $othersUuid = Incident::query()->where('created_by', $w['driver']->id)->value('uuid');
    $this->withHeaders(incHeaders($w['conductor']))->getJson('/api/v1/ops/incidents/'.$othersUuid)->assertNotFound();
});

it('throttles incident submissions to protect the alert channel', function (): void {
    $w = incWorld();
    $headers = incHeaders($w['conductor']);

    for ($i = 0; $i < 10; $i++) {
        $this->withHeaders($headers)->postJson('/api/v1/ops/incidents', incPayload($w['departure']))->assertCreated();
    }

    $this->withHeaders($headers)->postJson('/api/v1/ops/incidents', incPayload($w['departure']))->assertStatus(429);
});
