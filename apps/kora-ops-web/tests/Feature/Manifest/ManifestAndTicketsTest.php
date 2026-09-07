<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function manifestOperator(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director'); // departures.view + tickets.view + bookings.view

    return $user;
}

function manifestDriver(): User
{
    $user = User::factory()->create();
    $user->assignRole('bus_driver'); // departures.view ONLY — no tickets.view, no bookings.view

    return $user;
}

/** @return array{line: Line, origin: Stop, destination: Stop, bus: Bus} */
function manifestNetworkFixture(): array
{
    $line = Line::query()->create([
        'code' => 'MAN-L1',
        'name' => 'Cotonou - Lomé (manifest test)',
        'distance_km' => 170,
        'status' => 'active',
    ]);

    $originPhysical = PhysicalStop::query()->create([
        'code' => 'MAN-CTN', 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'code' => 'MAN-LFW', 'name' => 'Lomé', 'city' => 'Lomé', 'country' => 'TG', 'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou',
        'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Lomé',
        'city' => 'Lomé', 'country' => 'TG', 'stop_order' => 2, 'km_from_origin' => 170, 'status' => 'active',
    ]);

    $composition = BusComposition::query()->create([
        'code' => 'MAN-COMP', 'name' => 'Coach manifest test', 'seat_count' => 4,
        'layout' => [['type' => 'standard', 'codes' => ['1A', '1B', '1C', '1D']]], 'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'BJ-MANI-01', 'composition_id' => $composition->id, 'seat_count' => 4, 'status' => 'active',
    ]);

    return ['line' => $line, 'origin' => $origin, 'destination' => $destination, 'bus' => $bus];
}

function manifestDeparture(array $fixture, string $status = 'scheduled'): Departure
{
    return Departure::query()->create([
        'line_id' => $fixture['line']->id,
        'bus_id' => $fixture['bus']->id,
        'departure_date' => '2026-09-10',
        'departure_time' => '07:30',
        'status' => $status,
    ]);
}

/**
 * @return array{reservation: Reservation, passenger: ReservationPassenger, segment: ReservationSegment}
 */
function manifestPassenger(array $fixture, Departure $departure, array $overrides = []): array
{
    $reservation = Reservation::query()->create(array_merge([
        'status' => 'confirmed',
        'total_amount' => 5000,
        'currency' => 'XOF',
        'channel' => 'counter',
    ], $overrides['reservation'] ?? []));

    $passenger = ReservationPassenger::query()->create(array_merge([
        'reservation_id' => $reservation->id,
        'first_name' => 'Ama',
        'last_name' => 'Doe',
        'phone' => '+22990000000',
    ], $overrides['passenger'] ?? []));

    $seat = Seat::query()->create([
        'bus_id' => $fixture['bus']->id,
        'code' => $overrides['seat_code'] ?? '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $segment = ReservationSegment::query()->create(array_merge([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'leg_order' => 1,
        'departure_id' => $departure->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $fixture['origin']->id,
        'destination_stop_id' => $fixture['destination']->id,
        'origin_stop_order' => $fixture['origin']->stop_order,
        'destination_stop_order' => $fixture['destination']->stop_order,
        'status' => 'confirmed',
    ], $overrides['segment'] ?? []));

    return ['reservation' => $reservation, 'passenger' => $passenger, 'segment' => $segment];
}

it('distinguishes a pending hold from an expired hold', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);

    $activeHold = manifestPassenger($fixture, $departure, [
        'seat_code' => '1A',
        'reservation' => ['status' => 'held', 'expires_at' => now()->addMinutes(10)],
        'segment' => ['status' => 'held'],
    ]);
    $expiredHold = manifestPassenger($fixture, $departure, [
        'seat_code' => '1B',
        'reservation' => ['status' => 'held', 'expires_at' => now()->subMinutes(5)],
        'segment' => ['status' => 'held'],
    ]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers.0.passenger.uuid', $activeHold['passenger']->uuid)
            ->where('passengers.0.journeyStatus.value', 'hold_pending')
            ->where('passengers.1.passenger.uuid', $expiredHold['passenger']->uuid)
            ->where('passengers.1.journeyStatus.value', 'hold_expired'),
        );
});

it('lists passengers and tickets for a departure without exposing numeric ids', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);
    $entry = manifestPassenger($fixture, $departure, ['segment' => ['status' => 'ticketed']]);

    Ticket::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $entry['reservation']->id,
        'reservation_passenger_id' => $entry['passenger']->id,
        'qr_code' => 'QR-'.Str::random(10),
        'short_code' => 'TCK-0001',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('manifest/detail')
            ->where('departure.uuid', $departure->uuid)
            ->where('passengers.0.passenger.uuid', $entry['passenger']->uuid)
            ->where('passengers.0.passenger.firstName', 'Ama')
            ->where('passengers.0.ticket.shortCode', 'TCK-0001')
            ->where('passengers.0.journeyStatus.value', 'ticket_issued')
            ->missing('departure.id')
            ->missing('passengers.0.passenger.id')
            ->missing('passengers.0.ticket.id'),
        );
});

it('shows an empty passenger list for a departure with no reservations', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers', [])
            ->where('summary.total', 0),
        );
});

it('marks a passenger as boarded when their segment is boarded, even after departure', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture, 'departed');
    manifestPassenger($fixture, $departure, ['segment' => ['status' => 'boarded']]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers.0.journeyStatus.value', 'boarded'),
        );
});

it('marks a ticketed passenger as absent once the departure has left without them boarding', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture, 'departed');
    manifestPassenger($fixture, $departure, ['segment' => ['status' => 'ticketed']]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers.0.journeyStatus.value', 'absent'),
        );
});

it('does not mark a ticketed passenger as absent while the departure has not left yet', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture, 'scheduled');
    manifestPassenger($fixture, $departure, ['segment' => ['status' => 'ticketed']]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers.0.journeyStatus.value', 'ticket_issued'),
        );
});

it('distinguishes payment pending from payment confirmed for a confirmed segment', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);

    $pendingEntry = manifestPassenger($fixture, $departure, ['seat_code' => '1A']);
    $confirmedEntry = manifestPassenger($fixture, $departure, ['seat_code' => '1B']);

    Payment::query()->create([
        'reservation_id' => $confirmedEntry['reservation']->id,
        'provider' => 'sandbox-money',
        'provider_reference' => 'PAY-CONFIRMED',
        'idempotency_key' => 'manifest-test-confirmed',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passengers.0.passenger.uuid', $pendingEntry['passenger']->uuid)
            ->where('passengers.0.journeyStatus.value', 'payment_pending')
            ->where('passengers.1.passenger.uuid', $confirmedEntry['passenger']->uuid)
            ->where('passengers.1.journeyStatus.value', 'payment_confirmed'),
        );
});

it('keeps grouped-purchase passengers tied to their reservation while listing them individually', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);

    $reservation = Reservation::query()->create([
        'status' => 'confirmed', 'total_amount' => 10000, 'currency' => 'XOF', 'channel' => 'counter',
    ]);

    foreach ([['first_name' => 'Ama', 'seat' => '1A'], ['first_name' => 'Kofi', 'seat' => '1B']] as $data) {
        $passenger = ReservationPassenger::query()->create([
            'reservation_id' => $reservation->id, 'first_name' => $data['first_name'], 'last_name' => 'Group', 'phone' => '+22990000001',
        ]);
        $seat = Seat::query()->create(['bus_id' => $fixture['bus']->id, 'code' => $data['seat'], 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
        ReservationSegment::query()->create([
            'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id, 'leg_order' => 1,
            'departure_id' => $departure->id, 'seat_id' => $seat->id,
            'origin_stop_id' => $fixture['origin']->id, 'destination_stop_id' => $fixture['destination']->id,
            'origin_stop_order' => $fixture['origin']->stop_order, 'destination_stop_order' => $fixture['destination']->stop_order,
            'status' => 'confirmed',
        ]);
    }

    $this->actingAs(manifestOperator())
        ->get(route('manifest.show', $departure))
        ->assertInertia(fn (Assert $page) => $page
            ->has('passengers', 2)
            ->where('passengers.0.reservation.isGroupPurchase', true)
            ->where('passengers.1.reservation.isGroupPurchase', true)
            ->where('passengers.0.reservation.uuid', $reservation->uuid)
            ->where('passengers.1.reservation.uuid', $reservation->uuid),
        );

    $this->actingAs(manifestOperator())
        ->get(route('manifest.reservations.show', $reservation))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('manifest/reservation')
            ->where('reservation.isGroupPurchase', true)
            ->where('reservation.passengerCount', 2)
            ->has('passengers', 2),
        );
});

it('hides ticket details from a role with departures.view but not tickets.view', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);
    $entry = manifestPassenger($fixture, $departure, ['segment' => ['status' => 'ticketed']]);

    Ticket::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $entry['reservation']->id,
        'reservation_passenger_id' => $entry['passenger']->id,
        'qr_code' => 'QR-'.Str::random(10),
        'short_code' => 'TCK-HIDDEN',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    $this->actingAs(manifestDriver())
        ->get(route('manifest.show', $departure))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canViewTickets', false)
            ->where('passengers.0.ticket', null),
        );
});

it('forbids users without departures.view from accessing the manifest', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);

    $passenger = User::factory()->create();
    $passenger->assignRole('passenger');

    $this->actingAs($passenger)->get(route('manifest.index'))->assertForbidden();
    $this->actingAs($passenger)->get(route('manifest.show', $departure))->assertForbidden();
});

it('searches tickets by short code and shows the passenger status alongside the ticket status', function (): void {
    $fixture = manifestNetworkFixture();
    $departure = manifestDeparture($fixture);
    $entry = manifestPassenger($fixture, $departure, ['segment' => ['status' => 'ticketed']]);

    Ticket::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $entry['reservation']->id,
        'reservation_passenger_id' => $entry['passenger']->id,
        'qr_code' => 'QR-SEARCH',
        'short_code' => 'TCK-FINDME',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    $this->actingAs(manifestOperator())
        ->get(route('tickets.index', ['q' => 'findme']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.ticket.shortCode', 'TCK-FINDME')
            ->where('tickets.data.0.ticket.status', 'issued')
            ->where('tickets.data.0.journeyStatus.value', 'ticket_issued'),
        );
});
