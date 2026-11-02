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
use App\Modules\Ticketing\Actions\BuildTicketQrPayloadAction;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Str;

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
