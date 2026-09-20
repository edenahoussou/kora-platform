<?php

use App\Models\User;
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
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Payments\Models\Payment;
use App\Modules\SeatInventory\Actions\FindOccupiedSeatIdsAction;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function seatReleaseCrewUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{0: User, 1: string} */
function seatReleaseCrewToken(string $role = 'conductor'): array
{
    $user = seatReleaseCrewUser($role);
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$user, $token->plainTextToken];
}

function seatReleaseTerminal(string $serial = 'P501-RELEASE-01'): TerminalP501
{
    return TerminalP501::query()->create(['serial_number' => $serial]);
}

function seatReleaseLine(): Line
{
    return Line::query()->create([
        'code' => 'SR-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale', 'countries_crossed' => ['BJ'],
        'distance_km' => 40, 'estimated_duration_minutes' => 60, 'status' => 'active',
    ]);
}

/** @return array{0: Stop, 1: Stop, 2: Stop} A, B, C in canonical (stop_order) sequence */
function seatReleaseThreeStops(Line $line): array
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

/** @return array{0: Stop, 1: Stop, 2: Stop, 3: Stop} A, B, C, D in canonical (stop_order) sequence */
function seatReleaseFourStops(Line $line): array
{
    $stops = [];
    foreach ([['A', 'Cotonou', 1], ['B', 'Adjarra', 2], ['C', 'Sakete', 3], ['D', 'Porto-Novo', 4]] as [$name, $city, $order]) {
        $physical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => $name, 'city' => $city, 'country' => 'BJ', 'status' => 'active']);
        $stops[] = Stop::query()->create([
            'line_id' => $line->id, 'physical_stop_id' => $physical->id,
            'name' => $name, 'city' => $city, 'country' => 'BJ', 'stop_order' => $order, 'km_from_origin' => $order * 15, 'status' => 'active',
        ]);
    }

    return $stops;
}

/** @return array{0: Bus, 1: Seat} */
function seatReleaseBusWithSeat(): array
{
    $bus = Bus::query()->create(['registration_number' => 'SR-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    return [$bus, $seat];
}

/** @param array<string, int> $crew */
function seatReleaseDeparture(Line $line, Bus $bus, array $crew = [], string $status = 'boarding'): Departure
{
    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => $status,
        ...$crew,
    ]);
}

/**
 * @param  array<int, array{0: Departure, 1: Stop, 2: Stop, 3: Seat, 4: string}>  $legs  [departure, origin, destination, seat, segmentStatus]
 * @return array{0: Ticket, 1: ReservationPassenger, 2: array<int, ReservationSegment>}
 */
function seatReleaseTicketedPassenger(array $legs, string $ticketStatus = 'issued'): array
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

function seatReleasePayload(Departure $departure, Ticket $ticket, Stop $actualStop): array
{
    return [
        'departure_uuid' => $departure->uuid,
        'ticket_uuid' => $ticket->uuid,
        'actual_stop_uuid' => $actualStop->uuid,
        'operation_uuid' => (string) Str::uuid(),
        'local_recorded_at' => now()->toISOString(),
    ];
}

it('releases the boarded leg at the actual stop and never exposes financial data', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket, , $segments] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $segment = $segments[0]->refresh();
    expect($segment->status->value)->toBe('released')
        ->and($segment->actual_dropoff_stop_id)->toBe($b->id)
        ->and($segment->released_at)->not->toBeNull();

    $payload = json_encode($response->json());
    expect($payload)->not->toContain('amount')->not->toContain('price')->not->toContain('5000');
});

it('cascades release to subsequent legs but leaves earlier legs untouched', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c, $d] = seatReleaseFourStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $legA = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]); // A->B, already completed
    $legB = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]); // B->D, in progress
    $legC = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]); // D->A, future connection

    [$ticket, , $segments] = seatReleaseTicketedPassenger([
        [$legA, $a, $b, $seat, 'boarded'],
        [$legB, $b, $d, $seat, 'boarded'],
        [$legC, $d, $a, $seat, 'ticketed'],
    ]);

    // Descente à C : intermédiaire réel entre B (origine de la jambe en
    // cours) et D (sa destination) — une vraie descente anticipée, pas
    // la fin normale de la jambe.
    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($legB, $ticket, $c))
        ->assertOk();

    expect($segments[0]->refresh()->status->value)->toBe('boarded') // leg déjà complétée, jamais touchée
        ->and($segments[1]->refresh()->status->value)->toBe('released')
        ->and($segments[1]->refresh()->actual_dropoff_stop_id)->toBe($c->id)
        ->and($segments[2]->refresh()->status->value)->toBe('released')
        ->and($segments[2]->refresh()->actual_dropoff_stop_id)->toBe($d->id); // jamais embarquée : son propre point d'origine
});

it('replays the same operation_uuid without duplicating the release or the audit', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $payload = seatReleasePayload($departure, $ticket, $b);
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases', $payload)->assertOk();
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases', $payload)->assertOk();

    expect($first->json('data.uuid'))->toBe($second->json('data.uuid'))
        ->and(SeatReleaseEvent::query()->where('operation_uuid', $payload['operation_uuid'])->count())->toBe(1);
});

it('rejects an unknown ticket without persisting anything', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', [
            'departure_uuid' => $departure->uuid,
            'ticket_uuid' => (string) Str::uuid(),
            'actual_stop_uuid' => $b->uuid,
            'operation_uuid' => (string) Str::uuid(),
            'local_recorded_at' => now()->toISOString(),
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'ticket_unknown');

    expect(SeatReleaseEvent::query()->count())->toBe(0);
});

it('rejects a cancelled ticket', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']], ticketStatus: 'cancelled');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'ticket_cancelled');
});

it('rejects a ticket with no segment on the scanned departure', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $ticketedDeparture = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    $otherDeparture = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$ticketedDeparture, $a, $c, $seat, 'boarded']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($otherDeparture, $ticket, $b))
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'wrong_departure');
});

it('rejects an early disembark when the leg was never boarded (also covers expired segments)', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);

    foreach (['ticketed', 'expired'] as $status) {
        [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, $status]]);

        $this->withToken($token)
            ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
            ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
            ->assertStatus(409)
            ->assertJsonPath('data.code', 'segment_not_boarded')
            ->assertJsonPath('data.recommendedAction', 'Embarquez d’abord le billet (scan) avant de signaler une descente anticipée.');
    }
});

it('rejects a second release attempt on an already-released leg', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $headers = ['X-Terminal-Serial' => $terminal->serial_number];
    $this->withToken($token)->withHeaders($headers)
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))->assertOk();

    $this->withToken($token)->withHeaders($headers)
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertStatus(409)
        ->assertJsonPath('data.code', 'segment_already_released');
});

it('produces a deterministic conflict when two different terminals release the same ticket offline', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminalA = seatReleaseTerminal('P501-REL-A');
    $terminalB = seatReleaseTerminal('P501-REL-B');
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalA->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertOk()->assertJsonPath('data.status', 'accepted');

    $conflict = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminalB->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertStatus(409)
        ->assertJsonPath('data.status', 'conflict')
        ->assertJsonPath('data.code', 'seat_release_conflict');

    $conflictEvent = SeatReleaseEvent::query()->where('uuid', $conflict->json('data.uuid'))->first();
    expect($conflictEvent->conflict_payload['accepted_terminal_uuid'])->toBe($terminalA->uuid)
        ->and($conflictEvent->conflict_payload['conflicting_terminal_uuid'])->toBe($terminalB->uuid);
});

it('rejects a stop that is at or past the ticket destination, or on the wrong line', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);

    // Destination elle-même : ce n'est pas une descente "anticipée".
    [$ticket1] = seatReleaseTicketedPassenger([[$departure, $a, $b, $seat, 'boarded']]);
    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket1, $b))
        ->assertUnprocessable()->assertJsonPath('data.code', 'stop_not_compatible');

    // Arrêt d'une autre ligne.
    $otherLine = seatReleaseLine();
    [$foreignStop] = seatReleaseThreeStops($otherLine);
    [$ticket2] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);
    $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket2, $foreignStop))
        ->assertUnprocessable()->assertJsonPath('data.code', 'stop_not_compatible');
});

it('uses the departure circulation order, not the line canonical stop_order, for a reversed (return) departure', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line); // canonique A(1) B(2) C(3)
    [$bus, $seat] = seatReleaseBusWithSeat();

    // Départ retour : circulation C -> B -> A (indices 0, 1, 2).
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);
    $departure->update(['stop_times' => [
        ['stop_uuid' => $c->uuid], ['stop_uuid' => $b->uuid], ['stop_uuid' => $a->uuid],
    ]]);

    // Réservation C -> A : en indices de circulation, origin=0, destination=2.
    $reservation = Reservation::query()->create(['status' => 'ticketed', 'total_amount' => 5000, 'currency' => 'XOF', 'channel' => ReservationChannel::Counter]);
    $passenger = ReservationPassenger::query()->create(['reservation_id' => $reservation->id, 'status' => 'active', 'passenger_index' => 0, 'first_name' => 'Retour', 'last_name' => 'Trip']);
    $segment = ReservationSegment::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'leg_order' => 0, 'departure_id' => $departure->id, 'seat_id' => $seat->id,
        'origin_stop_id' => $c->id, 'destination_stop_id' => $a->id,
        'origin_stop_order' => 0, 'destination_stop_order' => 2, // indices de circulation, pas stop_order brut (3 et 1)
        'status' => 'boarded',
    ]);
    $ticket = Ticket::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-'.Str::random(24), 'short_code' => Str::upper(Str::random(6)), 'status' => 'issued', 'issued_at' => now(),
    ]);

    // B est entre C et A dans la circulation réelle (index 1), même si son
    // stop_order brut sur la ligne (2) ne le placerait pas correctement
    // entre les stop_order bruts de la réservation (3 et 1, décroissants).
    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($segment->refresh()->status->value)->toBe('released');
});

it('closes no-shows only once the departure has actually departed', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'boarding');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases/no-show-closure', [
            'departure_uuid' => $departure->uuid,
            'operation_uuid' => (string) Str::uuid(),
            'local_recorded_at' => now()->toISOString(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'departure_not_closable');
});

it('releases every ticketed-not-boarded passenger on closure, leaving boarded ones untouched', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat1] = seatReleaseBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');

    [, , $noShowSegments] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat1, 'ticketed']]);
    [, , $boardedSegments] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat2, 'boarded']]);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases/no-show-closure', [
            'departure_uuid' => $departure->uuid,
            'operation_uuid' => (string) Str::uuid(),
            'local_recorded_at' => now()->toISOString(),
        ])
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);

    $noShowSegment = $noShowSegments[0]->refresh();
    expect($noShowSegment->status->value)->toBe('released')
        ->and($noShowSegment->actual_dropoff_stop_id)->toBe($a->id) // jamais embarqué : son propre arrêt d'origine
        ->and($boardedSegments[0]->refresh()->status->value)->toBe('boarded');
});

it('cascades no-show release to subsequent legs of the same ticket', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $legA = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    $legB = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'scheduled');

    [, , $segments] = seatReleaseTicketedPassenger([
        [$legA, $a, $b, $seat, 'ticketed'],
        [$legB, $b, $c, $seat, 'ticketed'],
    ]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases/no-show-closure', [
            'departure_uuid' => $legA->uuid,
            'operation_uuid' => (string) Str::uuid(),
            'local_recorded_at' => now()->toISOString(),
        ])
        ->assertOk();

    expect($segments[0]->refresh()->status->value)->toBe('released')
        ->and($segments[1]->refresh()->status->value)->toBe('released');
});

it('replays the same no-show closure without duplicating releases or audit events', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'ticketed']]);

    $closurePayload = [
        'departure_uuid' => $departure->uuid,
        'operation_uuid' => (string) Str::uuid(),
        'local_recorded_at' => now()->toISOString(),
    ];
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases/no-show-closure', $closurePayload)->assertOk();
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases/no-show-closure', $closurePayload)->assertOk();

    expect(SeatReleaseEvent::query()->count())->toBe(1);
});

it('frees the seat for resale once released, without affecting a still-occupying segment', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat1] = seatReleaseBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id]);

    [$ticket1] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat1, 'boarded']]);
    seatReleaseTicketedPassenger([[$departure, $a, $c, $seat2, 'boarded']]);

    $before = app(FindOccupiedSeatIdsAction::class)->handle($departure, $a, $c);
    expect($before)->toContain($seat1->id)->toContain($seat2->id);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket1, $b))
        ->assertOk();

    $after = app(FindOccupiedSeatIdsAction::class)->handle($departure, $a, $c);
    expect($after)->not->toContain($seat1->id)
        ->and($after)->toContain($seat2->id);
});

it('denies release to a role without seat-releases.create, even if assigned to the departure', function (): void {
    [$user, $token] = seatReleaseCrewToken('bus_driver');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['driver_id' => $user->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertForbidden();
});

it('denies release for a departure the conductor is not assigned to', function (): void {
    [$user, $token] = seatReleaseCrewToken('conductor');
    $outsider = seatReleaseCrewUser('conductor');
    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, $b, $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $outsider->id]);
    [$ticket] = seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'boarded']]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->postJson('/api/v1/ops/seat-releases', seatReleasePayload($departure, $ticket, $b))
        ->assertForbidden();
});

it('notifies the crew and the agency ops director when no-shows are closed, once per closure', function (): void {
    Mail::fake();
    [$user, $token] = seatReleaseCrewToken('conductor');
    $agency = Agency::factory()->create();
    $user->forceFill(['agency_id' => $agency->id])->save();
    $director = User::factory()->create(['agency_id' => $agency->id]);
    $director->assignRole('operations_director');
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $terminal = seatReleaseTerminal();
    $line = seatReleaseLine();
    [$a, , $c] = seatReleaseThreeStops($line);
    [$bus, $seat] = seatReleaseBusWithSeat();
    $departure = seatReleaseDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    seatReleaseTicketedPassenger([[$departure, $a, $c, $seat, 'ticketed']]);

    $closure = [
        'departure_uuid' => $departure->uuid,
        'operation_uuid' => (string) Str::uuid(),
        'local_recorded_at' => now()->toISOString(),
    ];
    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases/no-show-closure', $closure)->assertOk();
    // Rejeu réseau de la même clôture, puis nouvelle clôture sans plus rien à libérer.
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases/no-show-closure', $closure)->assertOk();
    $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/ops/seat-releases/no-show-closure', [...$closure, 'operation_uuid' => (string) Str::uuid()])->assertOk();

    $events = OpsNotificationEvent::query()->where('type', 'no_show_closed')->get();
    $notifiedWith = fn (User $who): int => OpsNotification::query()
        ->where('user_id', $who->id)->whereHas('event', fn ($event) => $event->where('type', 'no_show_closed'))->count();

    expect($events)->toHaveCount(1)
        ->and($events[0]->dedupe_key)->toBe('no_show_closed:'.$closure['operation_uuid'])
        ->and($events[0]->data)->toBe(['closed_count' => 1])
        ->and($events[0]->departure_id)->toBe($departure->id)
        ->and($notifiedWith($user))->toBe(1)
        ->and($notifiedWith($director))->toBe(1)
        // Information simple : ni alerte pour les super-administrateurs, ni e-mail.
        ->and($notifiedWith($admin))->toBe(0);

    Mail::assertNothingSent();
});
