<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Tracking\Data\TrackingLiveState;
use App\Modules\Tracking\Data\TrackingSessionCloseReason;
use App\Modules\Tracking\Data\TrackingSessionStatus;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function trkUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/** @return array<string, string> */
function trkHeaders(User $user, string $serial = 'P501-TRK-01'): array
{
    // Le garde mémorise l'utilisateur de la requête précédente : sans cela, un 2e jeton
    // (autre utilisateur) dans le même test est rejeté (401). À chaque en-tête, on repart
    // d'un garde vierge (même convention que OpsIncidentTest).
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDay())->plainTextToken, 'X-Terminal-Serial' => $serial];
}

function trkDeparture(array $crew = [], array $overrides = []): Departure
{
    $line = Line::query()->create([
        'code' => 'TR-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Lomé',
        'type' => 'internationale', 'countries_crossed' => ['BJ', 'TG'],
        'distance_km' => 160, 'estimated_duration_minutes' => 180, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'TR-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);

    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHours(3), 'status' => 'departed',
        ...$crew,
        ...$overrides,
    ]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function trkPositions(int $count = 1, int $startSequence = 1, array $override = []): array
{
    return collect(range($startSequence, $startSequence + $count - 1))->map(fn (int $seq): array => [
        'position_uuid' => (string) Str::uuid(),
        'sequence' => $seq,
        'recorded_at' => now()->subSeconds(($startSequence + $count - $seq) * 10)->toISOString(),
        'latitude' => 6.36 + ($seq * 0.001),
        'longitude' => 2.41 + ($seq * 0.001),
        'accuracy_meters' => 8.5,
        'speed_kmh' => 60,
        'heading_degrees' => 120,
        'source' => 'gps',
        'status' => 'fix',
        ...$override,
    ])->all();
}

/** @return array{agency: Agency, terminal: TerminalP501, conductor: User, departure: Departure} */
function trkWorld(): array
{
    $agency = Agency::factory()->create();
    $conductor = trkUser('conductor', $agency);

    return [
        'agency' => $agency,
        'terminal' => TerminalP501::query()->create(['serial_number' => 'P501-TRK-01']),
        'conductor' => $conductor,
        'departure' => trkDeparture(['conductor_id' => $conductor->id]),
    ];
}

it('ingests a batch, opens a session and updates the last known position without duplicates on retry', function (): void {
    $w = trkWorld();
    $payload = ['departure_uuid' => $w['departure']->uuid, 'positions' => trkPositions(2)];

    $response = $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', $payload)->assertOk();

    expect($response->json('data.accepted'))->toBe(2)
        ->and($response->json('data.duplicate'))->toBe(0)
        ->and($response->json('data.rejected'))->toBe(0)
        ->and(TrackingSession::query()->count())->toBe(1)
        ->and(TrackingPosition::query()->count())->toBe(2);

    $session = TrackingSession::query()->sole();
    expect($session->status)->toBe(TrackingSessionStatus::Open)
        ->and($session->departure_id)->toBe($w['departure']->id)
        ->and($session->bus_id)->toBe($w['departure']->bus_id)
        ->and($session->terminal_p501_id)->toBe($w['terminal']->id)
        ->and($session->conductor_id)->toBe($w['conductor']->id)
        ->and($session->last_sequence)->toBe(2);

    // Rejeu intégral du même lot (coupure réseau côté terminal, ex. perte du
    // réseau avant réception de l'accusé) : aucun doublon créé.
    $replay = $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', $payload)->assertOk();

    expect($replay->json('data.accepted'))->toBe(0)
        ->and($replay->json('data.duplicate'))->toBe(2)
        ->and(TrackingPosition::query()->count())->toBe(2);
});

it('tolerates partial batches: an invalid timestamp is rejected without blocking the rest', function (): void {
    $w = trkWorld();

    $positions = trkPositions(1);
    $positions[0]['recorded_at'] = now()->subDays(2)->toISOString(); // trop ancien
    $positions[] = trkPositions(1, 2)[0];

    $response = $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => $positions,
    ])->assertOk();

    expect($response->json('data.accepted'))->toBe(1)
        ->and($response->json('data.rejected'))->toBe(1)
        ->and($response->json('data.rejected_details.0.reason'))->toBe('timestamp_too_old')
        ->and(TrackingPosition::query()->count())->toBe(1);
});

it('never lets an out-of-order position regress the last known state', function (): void {
    $w = trkWorld();
    $headers = trkHeaders($w['conductor']);

    $this->withHeaders($headers)->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => trkPositions(1, 5, ['latitude' => 6.40, 'longitude' => 2.45]),
    ])->assertOk();

    // Paquet en retard réseau : séquence 3, arrivé après la séquence 5.
    $this->withHeaders($headers)->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => trkPositions(1, 3, ['latitude' => 6.10, 'longitude' => 2.10]),
    ])->assertOk();

    $session = TrackingSession::query()->sole();
    expect($session->last_sequence)->toBe(5)
        ->and((float) $session->last_latitude)->toBe(6.40)
        ->and(TrackingPosition::query()->count())->toBe(2);
});

it('records a GPS-unavailable heartbeat without erasing the last known coordinates', function (): void {
    $w = trkWorld();
    $headers = trkHeaders($w['conductor']);

    $this->withHeaders($headers)->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => trkPositions(1, 1, ['latitude' => 6.40, 'longitude' => 2.45]),
    ])->assertOk();

    $this->withHeaders($headers)->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => [[
            'position_uuid' => (string) Str::uuid(),
            'sequence' => 2,
            'recorded_at' => now()->toISOString(),
            'status' => 'gps_unavailable',
            'source' => 'unknown',
        ]],
    ])->assertOk();

    $session = TrackingSession::query()->sole();
    expect($session->last_status->value)->toBe('gps_unavailable')
        ->and((float) $session->last_latitude)->toBe(6.40);
});

it('flags a low-accuracy position as degraded and reflects it as stale once old enough', function (): void {
    config(['tracking.degraded_accuracy_meters' => 20, 'tracking.stale_after_seconds' => 60]);

    $w = trkWorld();
    $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid,
        'positions' => trkPositions(1, 1, ['accuracy_meters' => 150, 'recorded_at' => now()->subSeconds(120)->toISOString()]),
    ])->assertOk();

    $position = TrackingPosition::query()->sole();
    expect($position->quality->value)->toBe('degraded');

    $director = trkUser('operations_director');
    $list = $this->withHeaders(trkHeaders($director))->getJson('/api/v1/fleet/tracking')->assertOk();

    expect($list->json('data.0.state'))->toBe(TrackingLiveState::Stale->value)
        ->and($list->json('data.0.position.quality'))->toBe('degraded');
});

it('answers unknown and not-assigned departures identically, and requires the tracking.report permission', function (): void {
    $w = trkWorld();
    $foreign = trkDeparture(['conductor_id' => trkUser('conductor')->id]);

    $notAssigned = $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $foreign->uuid, 'positions' => trkPositions(1),
    ])->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');

    $unknown = $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => (string) Str::uuid(), 'positions' => trkPositions(1),
    ])->assertForbidden()->assertJsonPath('error.code', 'departure_not_assigned');

    expect($notAssigned->json('error.message'))->toBe($unknown->json('error.message'))
        ->and(TrackingSession::query()->count())->toBe(0);

    Role::findByName('conductor', 'web')->revokePermissionTo('tracking.report');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid, 'positions' => trkPositions(1),
    ])->assertForbidden()->assertJsonPath('error.code', 'permission_denied');
});

it('closes the tracking session when the departure arrives or is cancelled', function (): void {
    $w = trkWorld();
    $this->withHeaders(trkHeaders($w['conductor']))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $w['departure']->uuid, 'positions' => trkPositions(1),
    ])->assertOk();

    $w['departure']->update(['status' => 'arrived', 'arrived_at' => now()]);

    $session = TrackingSession::query()->sole();
    expect($session->status)->toBe(TrackingSessionStatus::Closed)
        ->and($session->close_reason)->toBe(TrackingSessionCloseReason::Arrived);

    // Un départ annulé après coup ferme aussi une éventuelle nouvelle session.
    $other = trkDeparture(['conductor_id' => trkUser('conductor', $w['agency'])->id]);
    TerminalP501::query()->create(['serial_number' => 'P501-TRK-02']);
    $this->withHeaders(trkHeaders($other->conductor, 'P501-TRK-02'))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $other->uuid, 'positions' => trkPositions(1),
    ])->assertOk();

    $other->update(['status' => 'cancelled']);
    $otherSession = TrackingSession::query()->where('departure_id', $other->id)->sole();
    expect($otherSession->status)->toBe(TrackingSessionStatus::Closed)
        ->and($otherSession->close_reason)->toBe(TrackingSessionCloseReason::Cancelled);
});

it('scopes fleet/tracking by agency for an agency manager, and by network with an optional agency filter for network roles', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $conductorA = trkUser('conductor', $agencyA);
    $terminalA = TerminalP501::query()->create(['serial_number' => 'P501-TRK-A', 'agency_id' => $agencyA->id]);
    $departureA = trkDeparture(['conductor_id' => $conductorA->id]);
    $this->withHeaders(trkHeaders($conductorA, 'P501-TRK-A'))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $departureA->uuid, 'positions' => trkPositions(1),
    ])->assertOk();

    $conductorB = trkUser('conductor', $agencyB);
    $terminalB = TerminalP501::query()->create(['serial_number' => 'P501-TRK-B', 'agency_id' => $agencyB->id]);
    $departureB = trkDeparture(['conductor_id' => $conductorB->id]);
    $this->withHeaders(trkHeaders($conductorB, 'P501-TRK-B'))->postJson('/api/v1/ops/tracking/positions/batch', [
        'departure_uuid' => $departureB->uuid, 'positions' => trkPositions(1),
    ])->assertOk();

    $managerA = trkUser('agency_manager', $agencyA);
    $scoped = $this->withHeaders(trkHeaders($managerA))->getJson('/api/v1/fleet/tracking')->assertOk();
    expect($scoped->json('data'))->toHaveCount(1)
        ->and($scoped->json('data.0.departure.uuid'))->toBe($departureA->uuid)
        ->and(array_keys($scoped->json('data.0')))->not->toContain('id', 'departure_id', 'bus_id', 'terminal_p501_id', 'conductor_id');

    $director = trkUser('operations_director');
    $network = $this->withHeaders(trkHeaders($director))->getJson('/api/v1/fleet/tracking')->assertOk();
    expect($network->json('data'))->toHaveCount(2);

    $filtered = $this->withHeaders(trkHeaders($director))->getJson('/api/v1/fleet/tracking?agency='.$agencyB->uuid)->assertOk();
    expect($filtered->json('data'))->toHaveCount(1)
        ->and($filtered->json('data.0.departure.uuid'))->toBe($departureB->uuid);

    // Sans la permission tracking.view-agency/-network, aucune session n'est visible.
    $agent = trkUser('agency_agent', $agencyA);
    $this->withHeaders(trkHeaders($agent))->getJson('/api/v1/fleet/tracking')->assertOk()->assertJsonCount(0, 'data');
});
