<?php

use App\Models\User;
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
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function manifestCrewUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{0: User, 1: string} */
function manifestCrewToken(string $role = 'conductor'): array
{
    $user = manifestCrewUser($role);
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$user, $token->plainTextToken];
}

function manifestActiveTerminal(string $serial = 'P501-MANIFEST-01'): TerminalP501
{
    return TerminalP501::query()->create(['serial_number' => $serial]);
}

function manifestLine(bool $international = false): Line
{
    return Line::query()->create([
        'code' => 'ML-'.Str::upper(Str::random(4)),
        'name' => 'Cotonou - Lomé',
        'type' => $international ? 'internationale' : 'nationale',
        'countries_crossed' => $international ? ['BJ', 'TG'] : ['BJ'],
        'distance_km' => 150,
        'estimated_duration_minutes' => 180,
        'status' => 'active',
    ]);
}

/** @return array{0: Stop, 1: Stop} */
function manifestStops(Line $line, string $destinationCountry = 'BJ'): array
{
    $originPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou Gare',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysical->id,
        'name' => 'Cotonou Gare',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Destination Gare',
        'city' => 'Destination',
        'country' => $destinationCountry,
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysical->id,
        'name' => 'Destination Gare',
        'city' => 'Destination',
        'country' => $destinationCountry,
        'stop_order' => 2,
        'km_from_origin' => 150,
        'status' => 'active',
    ]);

    return [$origin, $destination];
}

/** @return array{0: Bus, 1: Seat} */
function manifestBusWithSeat(): array
{
    $bus = Bus::query()->create([
        'registration_number' => 'ML-BUS-'.Str::random(4),
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 1,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    return [$bus, $seat];
}

/** @param array<string, int> $crew */
function manifestOpsDeparture(Line $line, Bus $bus, array $crew = [], string $status = 'scheduled'): Departure
{
    return Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-01',
        'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHours(3),
        'status' => $status,
        ...$crew,
    ]);
}

/**
 * Crée une réservation + passager + segment + billet + paiement pour un
 * départ donné, et amène le segment/billet au statut interne demandé.
 *
 * @return array{0: ReservationSegment, 1: Ticket, 2: ReservationPassenger}
 */
function manifestBookedPassenger(
    Departure $departure,
    Stop $origin,
    Stop $destination,
    Seat $seat,
    string $segmentStatus = 'ticketed',
    string $ticketStatus = 'issued',
    array $passengerOverrides = [],
    bool $withConfirmedPayment = true,
): array {
    $reservation = Reservation::query()->create([
        'status' => 'ticketed',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Counter,
    ]);

    $passenger = ReservationPassenger::query()->create(array_merge([
        'reservation_id' => $reservation->id,
        'status' => 'active',
        'passenger_index' => 0,
        'first_name' => 'Awa',
        'last_name' => 'Traoré',
        'phone' => '+22900000000',
        'document_type' => 'passport',
        'document_number' => 'PP123456',
        'nationality' => 'BJ',
        'birth_date' => '1990-01-01',
        'yellow_fever_vaccinated' => true,
    ], $passengerOverrides));

    $segment = ReservationSegment::query()->create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
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
        'status' => $ticketStatus,
        'issued_at' => now(),
    ]);

    if ($withConfirmedPayment) {
        Payment::query()->create([
            'reservation_id' => $reservation->id,
            'provider' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 10000,
            'currency' => 'XOF',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    return [$segment, $ticket, $passenger];
}

it('lists only departures assigned to the crew member, excluding cancelled ones', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus] = manifestBusWithSeat();

    $assigned = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id], status: 'cancelled');
    manifestOpsDeparture($line, $bus); // not assigned to anyone

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson('/api/v1/ops/departures')
        ->assertOk();

    $uuids = collect($response->json('data'))->pluck('uuid');

    expect($uuids)->toHaveCount(1)->and($uuids->first())->toBe($assigned->uuid);
});

it('grants access to every crew role assigned to the departure, not just the conductor', function (): void {
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus] = manifestBusWithSeat();

    $cases = [
        ['bus_driver', 'driver_id'],
        ['bus_driver', 'secondary_driver_id'],
        ['mechanic', 'mechanic_id'],
        ['conductor', 'conductor_id'],
    ];

    foreach ($cases as [$role, $column]) {
        [$user, $token] = manifestCrewToken($role);
        $terminal = manifestActiveTerminal('P501-CREW-'.$column);
        $departure = manifestOpsDeparture($line, $bus, [$column => $user->id]);

        // Le guard sanctum met en cache l'utilisateur résolu sur son
        // instance, partagée entre les requêtes simulées d'un même test —
        // sans ce reset, les itérations suivantes réutiliseraient
        // l'authentification de la première au lieu du nouveau token.
        Auth::forgetGuards();

        $this->withToken($token)
            ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
            ->getJson("/api/v1/ops/departures/{$departure->uuid}")
            ->assertOk("Expected access for role [{$role}] via column [{$column}]");
    }
});

it('refuses a departure that is not assigned, cancelled, or otherwise out of scope', function (): void {
    [$outsider, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$bus] = manifestBusWithSeat();

    $notAssigned = manifestOpsDeparture($line, $bus);
    $cancelledButAssigned = manifestOpsDeparture($line, $bus, ['conductor_id' => $outsider->id], status: 'cancelled');

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$notAssigned->uuid}")
        ->assertForbidden();

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$cancelledButAssigned->uuid}")
        ->assertForbidden();
});

it('returns the departure detail with line, stops, bus, crew, status and manifest version — uuids only', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat] = manifestBusWithSeat();
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    manifestBookedPassenger($departure, $origin, $destination, $seat);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departure->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $departure->uuid)
        ->assertJsonPath('data.line.uuid', $line->uuid)
        ->assertJsonPath('data.line.international', false)
        ->assertJsonPath('data.bus.uuid', $bus->uuid)
        ->assertJsonPath('data.crew.conductor.uuid', $user->uuid)
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.line.id')
        ->assertJsonMissingPath('data.bus.id');

    expect($response->json('data.manifestVersion'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.stops.0.uuid'))->toBe($origin->uuid);
});

it('returns one manifest row per segment/passenger, only for the consulted departure leg', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $mid] = manifestStops($line);
    [$bus, $seat] = manifestBusWithSeat();

    // Two legs of the same multi-leg itinerary: departure A (origin->mid)
    // and departure B (mid->destination), same reservation/passenger.
    $departureA = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    $departureB = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);

    $reservation = Reservation::query()->create([
        'status' => 'ticketed', 'total_amount' => 20000, 'currency' => 'XOF', 'channel' => ReservationChannel::Counter,
    ]);
    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id, 'status' => 'active', 'passenger_index' => 0,
        'first_name' => 'Koffi', 'last_name' => 'Mensah',
    ]);
    $ticket = Ticket::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-'.Str::random(24), 'short_code' => 'MULTI1', 'status' => 'issued', 'issued_at' => now(),
    ]);
    Payment::query()->create([
        'reservation_id' => $reservation->id, 'provider' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        'amount' => 20000, 'currency' => 'XOF', 'status' => 'confirmed', 'confirmed_at' => now(),
    ]);
    ReservationSegment::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departureA->id, 'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id, 'destination_stop_id' => $mid->id,
        'origin_stop_order' => 1, 'destination_stop_order' => 2, 'status' => 'ticketed',
    ]);
    ReservationSegment::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departureB->id, 'seat_id' => $seat->id,
        'origin_stop_id' => $mid->id, 'destination_stop_id' => $origin->id,
        'origin_stop_order' => 2, 'destination_stop_order' => 3, 'status' => 'ticketed',
    ]);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departureA->uuid}/manifest")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.ticketUuid'))->toBe($ticket->uuid)
        ->and($response->json('data.0.name'))->toBe('Koffi Mensah');
});

it('never returns financial data to the crew', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat] = manifestBusWithSeat();
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    manifestBookedPassenger($departure, $origin, $destination, $seat);

    $response = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")
        ->assertOk();

    $payload = json_encode($response->json());

    expect($payload)->not->toContain('amount')
        ->not->toContain('price')
        ->not->toContain('10000')
        ->not->toContain('currency');
});

it('maps internal statuses to paid, boarded, cancelled and no_show', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat1] = manifestBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $seat3 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1C', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $seat4 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1D', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $seat5 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1E', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    // Pas encore parti : un segment "confirmed" avec paiement confirmé
    // lit "paid" (ResolvePassengerJourneyStatusAction ne considère un
    // ticketed-mais-non-embarqué comme absent qu'une fois le départ
    // effectivement parti/arrivé — voir le cas no_show séparé ci-dessous).
    $upcoming = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id], status: 'scheduled');

    manifestBookedPassenger($upcoming, $origin, $destination, $seat1, segmentStatus: 'confirmed', passengerOverrides: ['first_name' => 'Paid', 'last_name' => 'One']);
    manifestBookedPassenger($upcoming, $origin, $destination, $seat2, segmentStatus: 'boarded', passengerOverrides: ['first_name' => 'Boarded', 'last_name' => 'Two']);
    manifestBookedPassenger($upcoming, $origin, $destination, $seat3, segmentStatus: 'cancelled', passengerOverrides: ['first_name' => 'Cancelled', 'last_name' => 'Three']);
    // Held (pas encore payé) — doit être absent du manifeste.
    manifestBookedPassenger($upcoming, $origin, $destination, $seat4, segmentStatus: 'held', passengerOverrides: ['first_name' => 'Held', 'last_name' => 'Four'], withConfirmedPayment: false);

    $upcomingResponse = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$upcoming->uuid}/manifest")
        ->assertOk();

    $upcomingRows = collect($upcomingResponse->json('data'))->keyBy('name');

    expect($upcomingRows)->toHaveCount(3)
        ->and($upcomingRows['Paid One']['segmentStatus'])->toBe('paid')
        ->and($upcomingRows['Boarded Two']['segmentStatus'])->toBe('boarded')
        ->and($upcomingRows['Cancelled Three']['segmentStatus'])->toBe('cancelled')
        ->and($upcomingRows->has('Held Four'))->toBeFalse();

    // "no_show" ne s'applique qu'une fois le départ effectivement parti :
    // un billet émis mais jamais scanné à l'embarquement.
    $departed = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id], status: 'departed');
    manifestBookedPassenger($departed, $origin, $destination, $seat5, segmentStatus: 'ticketed', passengerOverrides: ['first_name' => 'NoShow', 'last_name' => 'Five']);

    $departedResponse = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departed->uuid}/manifest")
        ->assertOk();

    expect($departedResponse->json('data.0.segmentStatus'))->toBe('no_show');
});

it('searches manifest rows by passenger name and by ticket short code', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat1] = manifestBusWithSeat();
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);

    manifestBookedPassenger($departure, $origin, $destination, $seat1, passengerOverrides: ['first_name' => 'Fatou', 'last_name' => 'Diop']);
    [, $ticket2] = manifestBookedPassenger($departure, $origin, $destination, $seat2, passengerOverrides: ['first_name' => 'Issa', 'last_name' => 'Kone']);

    $byName = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest?q=fatou")
        ->assertOk();
    expect($byName->json('data'))->toHaveCount(1)->and($byName->json('data.0.name'))->toBe('Fatou Diop');

    $byCode = $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson('/api/v1/ops/departures/'.$departure->uuid.'/manifest?q='.$ticket2->short_code)
        ->assertOk();
    expect($byCode->json('data'))->toHaveCount(1)->and($byCode->json('data.0.ticketUuid'))->toBe($ticket2->uuid);
});

it('changes the manifest version when a segment status changes, and keeps it stable otherwise', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat] = manifestBusWithSeat();
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    [$segment] = manifestBookedPassenger($departure, $origin, $destination, $seat, segmentStatus: 'ticketed');

    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $first = $this->withToken($token)->withHeaders($headers)
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")->assertOk()->json('manifestVersion');
    $second = $this->withToken($token)->withHeaders($headers)
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")->assertOk()->json('manifestVersion');

    expect($first)->toBe($second);

    $segment->update(['status' => 'boarded']);

    $third = $this->withToken($token)->withHeaders($headers)
        ->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")->assertOk()->json('manifestVersion');

    expect($third)->not->toBe($first);
});

it('includes border-control fields only for international departures', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();

    $domesticLine = manifestLine(international: false);
    [$dOrigin, $dDestination] = manifestStops($domesticLine);
    [$bus, $seat1] = manifestBusWithSeat();
    $domesticDeparture = manifestOpsDeparture($domesticLine, $bus, ['conductor_id' => $user->id]);
    manifestBookedPassenger($domesticDeparture, $dOrigin, $dDestination, $seat1);

    $internationalLine = manifestLine(international: true);
    [$iOrigin, $iDestination] = manifestStops($internationalLine, destinationCountry: 'TG');
    $seat2 = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $internationalDeparture = manifestOpsDeparture($internationalLine, $bus, ['conductor_id' => $user->id]);
    manifestBookedPassenger($internationalDeparture, $iOrigin, $iDestination, $seat2);

    $domesticRow = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$domesticDeparture->uuid}/manifest")
        ->assertOk()->json('data.0');
    expect($domesticRow)->not->toHaveKey('document')
        ->not->toHaveKey('nationality')
        ->not->toHaveKey('birthDate')
        ->not->toHaveKey('yellowFeverVaccinated');

    $internationalRow = $this->withToken($token)->withHeaders(['X-Terminal-Serial' => $terminal->serial_number])
        ->getJson("/api/v1/ops/departures/{$internationalDeparture->uuid}/manifest")
        ->assertOk()->json('data.0');
    expect($internationalRow['nationality'])->toBe('BJ')
        ->and($internationalRow['document']['type'])->toBe('passport')
        ->and($internationalRow['yellowFeverVaccinated'])->toBeTrue();
});

it('is refused for a terminal that is not registered or not active', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $line = manifestLine();
    [$bus] = manifestBusWithSeat();
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);

    $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => 'UNKNOWN-TERMINAL'])
        ->getJson("/api/v1/ops/departures/{$departure->uuid}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown');
});

it('never leaks an internal numeric id, on the list, the detail, or the manifest', function (): void {
    [$user, $token] = manifestCrewToken('conductor');
    $terminal = manifestActiveTerminal();
    $line = manifestLine();
    [$origin, $destination] = manifestStops($line);
    [$bus, $seat] = manifestBusWithSeat();
    $departure = manifestOpsDeparture($line, $bus, ['conductor_id' => $user->id]);
    manifestBookedPassenger($departure, $origin, $destination, $seat);

    $headers = ['X-Terminal-Serial' => $terminal->serial_number];

    $index = $this->withToken($token)->withHeaders($headers)->getJson('/api/v1/ops/departures')->assertOk()->json();
    $show = $this->withToken($token)->withHeaders($headers)->getJson("/api/v1/ops/departures/{$departure->uuid}")->assertOk()->json();
    $manifest = $this->withToken($token)->withHeaders($headers)->getJson("/api/v1/ops/departures/{$departure->uuid}/manifest")->assertOk()->json();

    foreach (['index' => $index, 'show' => $show, 'manifest' => $manifest] as $label => $payload) {
        expect(manifestFindNumericIdKey($payload))->toBeNull("payload [{$label}] leaks a numeric 'id' key");
    }
});

/**
 * Recursively looks for an array key literally named "id" holding a
 * numeric value (a leaked internal primary key) — "uuid", "ticketUuid",
 * etc. are different keys and don't trip this.
 */
function manifestFindNumericIdKey(mixed $value): ?string
{
    if (! is_array($value)) {
        return null;
    }

    foreach ($value as $key => $item) {
        if ($key === 'id' && (is_int($item) || is_numeric($item))) {
            return 'id';
        }

        if ($found = manifestFindNumericIdKey($item)) {
            return $found;
        }
    }

    return null;
}
