<?php

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Payments\Models\Payment;
use App\Modules\Sync\Models\SyncBatch;
use App\Modules\Sync\Models\SyncOperation;
use App\Modules\Ticketing\Actions\BuildTicketQrPayloadAction;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    $keypair = sodium_crypto_sign_keypair();
    config([
        'tickets.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        'tickets.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
    ]);
});

function syncCrewUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{0: User, 1: string} */
function syncCrewToken(string $role = 'conductor'): array
{
    $user = syncCrewUser($role);
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$user, $token->plainTextToken];
}

function syncTerminal(string $serial = 'P501-SYNC-01'): TerminalP501
{
    return TerminalP501::query()->create(['serial_number' => $serial]);
}

function syncLine(): Line
{
    return Line::query()->create([
        'code' => 'SY-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale', 'countries_crossed' => ['BJ'],
        'distance_km' => 40, 'estimated_duration_minutes' => 60, 'status' => 'active',
    ]);
}

/** @return array{0: Stop, 1: Stop} */
function syncTwoStops(Line $line): array
{
    $stops = [];
    foreach ([['A', 'Cotonou', 1], ['B', 'Porto-Novo', 2]] as [$name, $city, $order]) {
        $physical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => $name, 'city' => $city, 'country' => 'BJ', 'status' => 'active']);
        $stops[] = Stop::query()->create([
            'line_id' => $line->id, 'physical_stop_id' => $physical->id,
            'name' => $name, 'city' => $city, 'country' => 'BJ', 'stop_order' => $order, 'km_from_origin' => $order * 20, 'status' => 'active',
        ]);
    }

    return $stops;
}

/** @return array{0: Stop, 1: Stop, 2: Stop} A, B (intermédiaire), C */
function syncThreeStops(Line $line): array
{
    $stops = [];
    foreach ([['A', 'Cotonou', 1], ['B', 'Adjarra', 2], ['C', 'Porto-Novo', 3]] as [$name, $city, $order]) {
        $physical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => $name, 'city' => $city, 'country' => 'BJ', 'status' => 'active']);
        $stops[] = Stop::query()->create([
            'line_id' => $line->id, 'physical_stop_id' => $physical->id,
            'name' => $name, 'city' => $city, 'country' => 'BJ', 'stop_order' => $order, 'km_from_origin' => $order * 15, 'status' => 'active',
        ]);
    }

    return $stops;
}

/** @return array{0: Bus, 1: Seat} */
function syncBusWithSeat(): array
{
    $bus = Bus::query()->create(['registration_number' => 'SY-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    return [$bus, $seat];
}

/** @param array<string, int> $crew */
function syncDeparture(Line $line, Bus $bus, array $crew = [], string $status = 'boarding'): Departure
{
    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => $status,
        ...$crew,
    ]);
}

/**
 * @return array{0: Ticket, 1: ReservationPassenger, 2: ReservationSegment}
 */
function syncTicketedPassenger(Departure $departure, Stop $origin, Stop $destination, Seat $seat, string $segmentStatus = 'ticketed', string $ticketStatus = 'issued'): array
{
    $reservation = Reservation::query()->create([
        'status' => 'ticketed', 'total_amount' => 5000, 'currency' => 'XOF', 'channel' => ReservationChannel::Counter,
    ]);
    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id, 'status' => 'active', 'passenger_index' => 0,
        'first_name' => 'Fatou', 'last_name' => 'Diop',
    ]);
    Payment::query()->create([
        'reservation_id' => $reservation->id, 'provider' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        'amount' => 5000, 'currency' => 'XOF', 'status' => 'confirmed', 'confirmed_at' => now(),
    ]);
    $segment = ReservationSegment::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'leg_order' => 0, 'departure_id' => $departure->id, 'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id,
        'origin_stop_order' => $origin->stop_order, 'destination_stop_order' => $destination->stop_order,
        'status' => $segmentStatus,
    ]);
    $ticket = Ticket::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-'.Str::random(24), 'short_code' => Str::upper(Str::random(6)),
        'status' => $ticketStatus, 'issued_at' => now(),
    ]);

    return [$ticket, $passenger, $segment];
}

function syncBoardingScanOperation(Departure $departure, Ticket $ticket): array
{
    return [
        'operation_uuid' => (string) Str::uuid(),
        'operation_type' => 'boarding.scan',
        'local_recorded_at' => now()->toISOString(),
        'payload' => [
            'departure_uuid' => $departure->uuid,
            'qr_payload' => app(BuildTicketQrPayloadAction::class)->execute($ticket->reservation, $ticket),
        ],
    ];
}

function syncSeatReleaseOperation(Departure $departure, Ticket $ticket, Stop $actualStop): array
{
    return [
        'operation_uuid' => (string) Str::uuid(),
        'operation_type' => 'seat_release',
        'local_recorded_at' => now()->toISOString(),
        'payload' => [
            'departure_uuid' => $departure->uuid,
            'ticket_uuid' => $ticket->uuid,
            'actual_stop_uuid' => $actualStop->uuid,
        ],
    ];
}

function syncNoShowOperation(Departure $departure, Ticket $ticket): array
{
    return [
        'operation_uuid' => (string) Str::uuid(),
        'operation_type' => 'boarding.no_show',
        'local_recorded_at' => now()->toISOString(),
        'payload' => [
            'departure_uuid' => $departure->uuid,
            'ticket_uuid' => $ticket->uuid,
        ],
    ];
}

it('processes each operation independently in a partially successful batch', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat1] = syncBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);

    [$goodTicket] = syncTicketedPassenger($departure, $a, $b, $seat1, 'ticketed');
    [$cancelledTicket] = syncTicketedPassenger($departure, $a, $b, $seat2, 'ticketed', ticketStatus: 'cancelled');

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [
                syncBoardingScanOperation($departure, $goodTicket),
                syncSeatReleaseOperation($departure, $cancelledTicket, $b),
            ],
        ])
        ->assertOk();

    $results = collect($response->json('data.operations'))->keyBy('operationType');

    expect($response->json('data.summary'))->toBe(['accepted' => 1, 'pending' => 0, 'conflict' => 0, 'rejected' => 1])
        ->and($results['boarding.scan']['status'])->toBe('accepted')
        ->and($results['seat_release']['status'])->toBe('rejected')
        ->and($results['seat_release']['result']['code'])->toBe('ticket_cancelled');
});

it('never creates a duplicate event when the same batch is replayed', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $payload = [
        'batch_uuid' => (string) Str::uuid(),
        'operations' => [syncBoardingScanOperation($departure, $ticket)],
    ];
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/sync/batches', $payload)->assertOk();
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/sync/batches', $payload)->assertOk();

    expect($first->json('data.uuid'))->toBe($second->json('data.uuid'))
        ->and(SyncBatch::query()->count())->toBe(1)
        ->and(SyncOperation::query()->count())->toBe(1)
        ->and(BoardingEvent::query()->count())->toBe(1);
});

it('restores the local queue deterministically after a crash-and-retry with a new batch id but the same operations', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $operation = syncBoardingScanOperation($departure, $ticket);
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    // Premier envoi, le terminal "crashe" avant de recevoir la réponse.
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/sync/batches', [
        'batch_uuid' => (string) Str::uuid(),
        'operations' => [$operation],
    ])->assertOk();

    // Redémarrage : nouveau batch_uuid, mais la même opération en file
    // locale (son operation_uuid n'a pas changé).
    $retry = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/sync/batches', [
        'batch_uuid' => (string) Str::uuid(),
        'operations' => [$operation],
    ])->assertOk();

    expect($retry->json('data.operations.0.status'))->toBe('accepted')
        ->and(BoardingEvent::query()->count())->toBe(1)
        ->and(SyncBatch::query()->count())->toBe(2) // deux lots, mais...
        ->and(SyncOperation::query()->count())->toBe(1); // ...une seule opération réellement traitée
});

it('makes a scan conflict between two terminals visible for both mobile and ops web', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminalA = syncTerminal('P501-SYNC-A');
    $terminalB = syncTerminal('P501-SYNC-B');
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $qr = app(BuildTicketQrPayloadAction::class)->execute($ticket->reservation, $ticket);

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalA->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [[
                'operation_uuid' => (string) Str::uuid(), 'operation_type' => 'boarding.scan',
                'local_recorded_at' => now()->toISOString(),
                'payload' => ['departure_uuid' => $departure->uuid, 'qr_payload' => $qr],
            ]],
        ])->assertOk()->assertJsonPath('data.operations.0.status', 'accepted');

    $conflictResponse = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalB->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [[
                'operation_uuid' => (string) Str::uuid(), 'operation_type' => 'boarding.scan',
                'local_recorded_at' => now()->toISOString(),
                'payload' => ['departure_uuid' => $departure->uuid, 'qr_payload' => $qr],
            ]],
        ])->assertOk();

    // Visible côté mobile (résultat de l'opération)...
    expect($conflictResponse->json('data.operations.0.status'))->toBe('conflict');
    // ...et côté OPS Web (la table boarding_events elle-même, que l'écran
    // d'administration interroge directement).
    expect(BoardingEvent::query()->where('status', 'conflict')->exists())->toBeTrue();
});

it('detects a stale manifest version and reports inaccessible departures without leaking their existence', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $outsider = syncCrewUser('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $mine = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    $notMine = syncDeparture($line, $bus, ['conductor_id' => $outsider->id]);
    syncTicketedPassenger($mine, $a, $b, $seat, 'ticketed');

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/manifest-versions', [
            'departures' => [
                ['departure_uuid' => $mine->uuid, 'known_version' => 'obviously-stale-hash'],
                ['departure_uuid' => $notMine->uuid, 'known_version' => null],
            ],
        ])
        ->assertOk();

    $results = collect($response->json('data'))->keyBy('departureUuid');

    expect($results[$mine->uuid]['stale'])->toBeTrue()
        ->and($results[$mine->uuid]['accessible'])->toBeTrue()
        ->and($results[$mine->uuid]['currentVersion'])->not->toBeNull()
        ->and($results[$notMine->uuid]['accessible'])->toBeFalse()
        ->and($results[$notMine->uuid]['currentVersion'])->toBeNull();
});

it('reports a manifest version as not stale once the known version matches', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $manifestResponse = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")
        ->assertOk();

    $currentVersion = $manifestResponse->json('manifestVersion');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/manifest-versions', [
            'departures' => [['departure_uuid' => $departure->uuid, 'known_version' => $currentVersion]],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.stale', false);
});

it('processes seat releases before boarding scans within the same batch, regardless of submission order', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b, $c] = syncThreeStops($line);
    [$bus, $seat1] = syncBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);

    [$scanTicket] = syncTicketedPassenger($departure, $a, $c, $seat1, 'ticketed');
    [$releaseTicket] = syncTicketedPassenger($departure, $a, $c, $seat2, 'boarded');

    $scanOp = syncBoardingScanOperation($departure, $scanTicket);
    $releaseOp = syncSeatReleaseOperation($departure, $releaseTicket, $b);

    // Soumis dans l'ordre [scan, release] — l'attendu est qu'il soit
    // traité dans l'ordre [release, scan].
    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [$scanOp, $releaseOp],
        ])
        ->assertOk();

    $releaseRowId = SyncOperation::query()->where('operation_uuid', $releaseOp['operation_uuid'])->value('id');
    $scanRowId = SyncOperation::query()->where('operation_uuid', $scanOp['operation_uuid'])->value('id');

    expect($releaseRowId)->toBeLessThan($scanRowId);
});

it('handles a large batch, returning one independent result per operation', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);

    $operations = [];
    for ($i = 0; $i < 30; $i++) {
        $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => 'S'.$i, 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
        [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');
        $operations[] = syncBoardingScanOperation($departure, $ticket);
    }

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => $operations,
        ])
        ->assertOk();

    expect($response->json('data.operations'))->toHaveCount(30)
        ->and($response->json('data.summary.accepted'))->toBe(30);
});

it('accepts an individual no-show for a ticketed-not-boarded passenger once the departure has departed', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    [$ticket, , $segment] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncNoShowOperation($departure, $ticket)],
        ])
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'accepted');

    expect($segment->refresh()->status->value)->toBe('released')
        ->and(SeatReleaseEvent::query()->count())->toBe(1);
});

it('rejects an individual no-show for a passenger who actually boarded', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'boarded');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncNoShowOperation($departure, $ticket)],
        ])
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'rejected')
        ->assertJsonPath('data.operations.0.result.code', 'segment_not_eligible_for_no_show');
});

it('rejects an individual no-show before the departure has actually departed', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id], status: 'boarding');
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncNoShowOperation($departure, $ticket)],
        ])
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'rejected')
        ->assertJsonPath('data.operations.0.result.code', 'departure_not_yet_closable');
});

it('rejects an operation for a role lacking the required permission, without failing the whole batch', function (): void {
    [$user, $token] = syncCrewToken('bus_driver');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['driver_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncBoardingScanOperation($departure, $ticket)],
        ])
        ->assertOk();

    expect($response->json('data.operations.0.status'))->toBe('rejected')
        ->and($response->json('data.operations.0.result.code'))->toBe('permission_denied');
});

it('never exposes financial data in sync responses', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncBoardingScanOperation($departure, $ticket)],
        ])
        ->assertOk();

    $payload = json_encode($response->json());
    expect($payload)->not->toContain('amount')->not->toContain('price')->not->toContain('5000');
});

it('makes a seat-release conflict between two terminals visible for both mobile and ops web', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminalA = syncTerminal('P501-SYNC-REL-A');
    $terminalB = syncTerminal('P501-SYNC-REL-B');
    $line = syncLine();
    [$a, $b, $c] = syncThreeStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $c, $seat, 'boarded');

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalA->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncSeatReleaseOperation($departure, $ticket, $b)],
        ])->assertOk()->assertJsonPath('data.operations.0.status', 'accepted');

    $conflictResponse = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalB->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncSeatReleaseOperation($departure, $ticket, $b)],
        ])->assertOk();

    expect($conflictResponse->json('data.operations.0.status'))->toBe('conflict');
    expect(SeatReleaseEvent::query()->where('status', 'conflict')->exists())->toBeTrue();
});

it('updates the terminal last_sync_at after processing a batch', function (): void {
    [$user, $token] = syncCrewToken('conductor');
    $terminal = syncTerminal();
    $line = syncLine();
    [$a, $b] = syncTwoStops($line);
    [$bus, $seat] = syncBusWithSeat();
    $departure = syncDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = syncTicketedPassenger($departure, $a, $b, $seat, 'ticketed');

    expect($terminal->last_sync_at)->toBeNull();

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/sync/batches', [
            'batch_uuid' => (string) Str::uuid(),
            'operations' => [syncBoardingScanOperation($departure, $ticket)],
        ])
        ->assertOk();

    expect($terminal->refresh()->last_sync_at)->not->toBeNull();
});
