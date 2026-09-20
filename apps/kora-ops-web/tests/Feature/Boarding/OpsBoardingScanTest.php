<?php

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
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
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Actions\BuildTicketQrPayloadAction;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    $keypair = sodium_crypto_sign_keypair();
    config([
        'tickets.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        'tickets.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
    ]);
});

function boardingCrewUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{0: User, 1: string} */
function boardingCrewToken(string $role = 'conductor'): array
{
    $user = boardingCrewUser($role);
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$user, $token->plainTextToken];
}

function boardingTerminal(string $serial = 'P501-BOARDING-01'): TerminalP501
{
    return TerminalP501::query()->create(['serial_number' => $serial]);
}

function boardingLine(): Line
{
    return Line::query()->create([
        'code' => 'BL-'.Str::upper(Str::random(4)),
        'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale',
        'countries_crossed' => ['BJ'],
        'distance_km' => 40,
        'estimated_duration_minutes' => 60,
        'status' => 'active',
    ]);
}

/** @return array{0: Stop, 1: Stop} */
function boardingStops(Line $line): array
{
    $originPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Cotonou Gare', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $originPhysical->id,
        'name' => 'Cotonou Gare', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Porto-Novo Gare', 'city' => 'Porto-Novo', 'country' => 'BJ', 'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id,
        'name' => 'Porto-Novo Gare', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 40, 'status' => 'active',
    ]);

    return [$origin, $destination];
}

/** @return array{0: Bus, 1: Seat} */
function boardingBusWithSeat(): array
{
    $bus = Bus::query()->create(['registration_number' => 'BL-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    return [$bus, $seat];
}

/** @param array<string, int> $crew */
function boardingDeparture(Line $line, Bus $bus, array $crew = [], string $status = 'boarding'): Departure
{
    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => $status,
        ...$crew,
    ]);
}

/**
 * Une réservation + passager + billet, avec un segment par (départ, seat)
 * fourni — supporte le multi-jambes en passant plusieurs départs.
 *
 * @param  array<int, array{0: Departure, 1: Stop, 2: Stop, 3: Seat, 4: string}>  $legs  [departure, origin, destination, seat, segmentStatus]
 * @return array{0: Ticket, 1: ReservationPassenger, 2: array<int, ReservationSegment>}
 */
function boardingTicketedPassenger(array $legs, string $ticketStatus = 'issued'): array
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

    $segments = [];
    foreach ($legs as $legOrder => [$departure, $origin, $destination, $seat, $segmentStatus]) {
        $segments[] = ReservationSegment::query()->create([
            'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
            'leg_order' => $legOrder, 'departure_id' => $departure->id, 'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order, 'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
        ]);
    }

    $ticket = Ticket::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-'.Str::random(24), 'short_code' => Str::upper(Str::random(6)),
        'status' => $ticketStatus, 'issued_at' => now(),
    ]);

    return [$ticket, $passenger, $segments];
}

function boardingQrFor(Ticket $ticket): string
{
    return app(BuildTicketQrPayloadAction::class)->execute($ticket->reservation, $ticket);
}

/** @return array{string, string} [X-Terminal-Serial header value key already included, payload] */
function boardingScanPayload(Departure $departure, Ticket $ticket, ?string $shortCode = null): array
{
    return [
        'departure_uuid' => $departure->uuid,
        'qr_payload' => $shortCode === null ? boardingQrFor($ticket) : null,
        'short_code' => $shortCode,
        'operation_uuid' => (string) Str::uuid(),
        'local_recorded_at' => now()->toISOString(),
    ];
}

it('accepts a valid scan by qr, boards the segment, and never exposes financial data', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket, , $segments] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.ticketUuid', $ticket->uuid);

    expect($segments[0]->refresh()->status->value)->toBe('boarded');

    $payload = json_encode($response->json());
    expect($payload)->not->toContain('amount')->not->toContain('price')->not->toContain('5000');
});

it('accepts a valid scan by manually-entered short code', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket, shortCode: $ticket->short_code))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

it('replays the same result on a retry with the same operation_uuid, without side effects', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $payload = boardingScanPayload($departure, $ticket);
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/boarding', $payload)->assertOk();
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/boarding', $payload)->assertOk();

    expect($first->json('data.uuid'))->toBe($second->json('data.uuid'))
        ->and(BoardingEvent::query()->where('operation_uuid', $payload['operation_uuid'])->count())->toBe(1);
});

it('rejects an unknown or tampered qr without persisting anything', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$bus] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', [
            'departure_uuid' => $departure->uuid,
            'qr_payload' => 'garbage.notasignature',
            'operation_uuid' => (string) Str::uuid(),
            'local_recorded_at' => now()->toISOString(),
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'qr_unknown');

    expect(BoardingEvent::query()->count())->toBe(0);
});

it('rejects a cancelled ticket', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']], ticketStatus: 'cancelled');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.code', 'ticket_cancelled');
});

it('rejects a ticket that has no segment for the scanned departure', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $ticketedDeparture = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    $otherDeparture = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$ticketedDeparture, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($otherDeparture, $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'wrong_departure');
});

it('rejects a leg that is not active (not in ticketed status)', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'held']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'leg_not_active');
});

it('rejects boarding when the passenger itself is cancelled, even if the segment status lags behind', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket, $passenger] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    // Simule une désynchronisation : le passager est annulé directement
    // sans passer par CancelReservationAction (qui aurait normalement
    // aussi basculé le segment/billet). La vérification d'éligibilité
    // doit intercepter ce cas indépendamment du statut du segment.
    $passenger->update(['status' => 'cancelled']);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'leg_not_active');
});

it('rejects a second scan of the same ticket on the same terminal as already boarded', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $headers = ['X-Terminal-Serial' => $terminal->serial_number];
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))->assertOk();

    $this->withToken($token)->withHeaders($headers)
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertStatus(409)
        ->assertJsonPath('data.code', 'ticket_already_boarded');
});

it('produces a deterministic conflict when two different terminals board the same ticket offline', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminalA = boardingTerminal('P501-TERM-A');
    $terminalB = boardingTerminal('P501-TERM-B');
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket, , $segments] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalA->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertOk()->assertJsonPath('data.status', 'accepted');

    $conflictResponse = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalB->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertStatus(409)
        ->assertJsonPath('data.status', 'conflict')
        ->assertJsonPath('data.code', 'boarding_conflict');

    // Le segment reste embarqué au bénéfice du premier terminal — le
    // conflit ne l'annule pas rétroactivement.
    expect($segments[0]->refresh()->status->value)->toBe('boarded');

    $conflictEvent = BoardingEvent::query()->where('uuid', $conflictResponse->json('data.uuid'))->first();
    expect($conflictEvent->conflict_payload['accepted_terminal_uuid'])->toBe($terminalA->uuid)
        ->and($conflictEvent->conflict_payload['conflicting_terminal_uuid'])->toBe($terminalB->uuid);
});

it('boards only the scanned leg of a multi-leg ticket, leaving the other leg untouched', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $mid] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $legA = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    $legB = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket, , $segments] = boardingTicketedPassenger([
        [$legA, $origin, $mid, $seat, 'ticketed'],
        [$legB, $mid, $origin, $seat, 'ticketed'],
    ]);

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($legA, $ticket))
        ->assertOk();

    expect($segments[0]->refresh()->status->value)->toBe('boarded')
        ->and($segments[1]->refresh()->status->value)->toBe('ticketed');
});

it('denies scanning to a role without boarding.scan, even if assigned to the departure', function (): void {
    [$user, $token] = boardingCrewToken('bus_driver');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['driver_id' => $user->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertForbidden();
});

it('denies scanning a departure the conductor is not assigned to', function (): void {
    [$user, $token] = boardingCrewToken('conductor');
    $outsider = boardingCrewUser('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $outsider->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertForbidden();
});

it('reverses an accepted boarding with a dedicated permission, a reason, and an immutable audit trail', function (): void {
    [$conductor, $token] = boardingCrewToken('conductor');
    $admin = boardingCrewUser('operations_director');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $conductor->id]);
    [$ticket, , $segments] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $scan = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertOk();

    $boardingEventUuid = $scan->json('data.uuid');

    Auth::forgetGuards();

    $this->actingAs($admin)
        ->postJson("/api/v1/ops/boarding/{$boardingEventUuid}/reverse", ['reason' => 'Scan erroné, passager non présent à bord.'])
        ->assertOk();

    expect($segments[0]->refresh()->status->value)->toBe('ticketed');

    $original = BoardingEvent::query()->where('uuid', $boardingEventUuid)->first();
    expect($original->status->value)->toBe('accepted'); // jamais modifié

    $activity = Activity::query()->where('description', 'boarding_event_reversed')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['reason'])->toBe('Scan erroné, passager non présent à bord.');
});

it('denies the exceptional reversal to a conductor (lacks boarding.reverse)', function (): void {
    [$conductor, $token] = boardingCrewToken('conductor');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $conductor->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $scan = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertOk();

    Auth::forgetGuards();

    $this->withToken($token)
        ->postJson("/api/v1/ops/boarding/{$scan->json('data.uuid')}/reverse", ['reason' => 'Test.'])
        ->assertForbidden();
});

it('requires a reason to reverse a boarding', function (): void {
    $admin = boardingCrewUser('operations_director');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    [$conductor, $token] = boardingCrewToken('conductor');
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $conductor->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $scan = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertOk();

    Auth::forgetGuards();

    $this->actingAs($admin)
        ->postJson("/api/v1/ops/boarding/{$scan->json('data.uuid')}/reverse", [])
        ->assertUnprocessable();
});

it('refuses to reverse a boarding event that was not accepted (rejected or conflict)', function (): void {
    $admin = boardingCrewUser('operations_director');
    $terminal = boardingTerminal();
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    [$conductor, $token] = boardingCrewToken('conductor');
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $conductor->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']], ticketStatus: 'cancelled');

    $rejectedScan = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('data.status', 'rejected');

    Auth::forgetGuards();

    $this->actingAs($admin)
        ->postJson("/api/v1/ops/boarding/{$rejectedScan->json('data.uuid')}/reverse", ['reason' => 'Test.'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'boarding_event_not_reversible');
});

it('raises a critical scan-conflict alert for the crew, the agency ops director and the superadmins, with an e-mail', function (): void {
    Mail::fake();
    [$user, $token] = boardingCrewToken('conductor');
    $agency = Agency::factory()->create();
    $user->forceFill(['agency_id' => $agency->id])->save();
    $director = User::factory()->create(['agency_id' => $agency->id]);
    $director->assignRole('operations_director');
    $otherAgencyDirector = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);
    $otherAgencyDirector->assignRole('operations_director');
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    [$bystander] = boardingCrewToken('conductor');

    $terminalA = boardingTerminal('P501-CONF-A');
    $terminalB = boardingTerminal('P501-CONF-B');
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, ['conductor_id' => $user->id]);
    boardingDeparture($line, $bus, ['conductor_id' => $bystander->id]);
    [$ticket] = boardingTicketedPassenger([[$departure, $origin, $destination, $seat, 'ticketed']]);

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalA->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))->assertOk();

    // Le scan accepté n'est pas un conflit : rien à signaler à ce stade.
    expect(OpsNotificationEvent::query()->where('type', 'scan_conflict')->count())->toBe(0);

    $conflict = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalB->serial_number])
        ->postJson('/api/v1/ops/boarding', boardingScanPayload($departure, $ticket))
        ->assertStatus(409);

    $events = OpsNotificationEvent::query()->where('type', 'scan_conflict')->get();
    $notifiedWith = fn (User $who): int => OpsNotification::query()
        ->where('user_id', $who->id)->whereHas('event', fn ($event) => $event->where('type', 'scan_conflict'))->count();

    expect($events)->toHaveCount(1)
        ->and($events[0]->dedupe_key)->toBe('scan_conflict:'.$conflict->json('data.uuid'))
        ->and($events[0]->severity->value)->toBe('critical')
        ->and($events[0]->departure_id)->toBe($departure->id)
        ->and($events[0]->data['code'])->toBe('boarding_conflict')
        ->and($events[0]->data['accepted_terminal_serial'])->toBe('P501-CONF-A')
        ->and($events[0]->data['conflicting_terminal_serial'])->toBe('P501-CONF-B')
        ->and($notifiedWith($user))->toBe(1)
        ->and($notifiedWith($director))->toBe(1)
        ->and($notifiedWith($admin))->toBe(1)
        // Ni un autre départ, ni une autre agence.
        ->and($notifiedWith($bystander))->toBe(0)
        ->and($notifiedWith($otherAgencyDirector))->toBe(0);

    Mail::assertSent(
        OpsAlertMail::class,
        fn ($mail): bool => $mail->hasTo($director->email) && $mail->hasCc($admin->email) && ! $mail->hasTo($otherAgencyDirector->email),
    );
    Mail::assertSent(OpsAlertMail::class, 1);
});
