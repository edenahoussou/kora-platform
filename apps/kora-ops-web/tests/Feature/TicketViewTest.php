<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;

function ticketViewScenario(): array
{
    test()->seed(KoraBusRolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('agency_agent');
    $agency = Agency::create(['code' => 'COT01', 'name' => 'Cotonou Agence', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $user->agency()->associate($agency);
    $user->save();

    $originPhysical = PhysicalStop::create(['name' => 'Cotonou Dantokpa', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::create(['name' => 'Djougou Centre', 'city' => 'Djougou', 'country' => 'BJ', 'status' => 'active']);

    $line = Line::create(['code' => 'K01', 'name' => 'Cotonou-Djougou', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $origin = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Djougou', 'city' => 'Djougou', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

    $bus = Bus::create(['registration_number' => 'BJ-1234-AA', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 2, 'status' => 'active']);
    $seat = Seat::create(['bus_id' => $bus->id, 'code' => '12A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    $departure = Departure::create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-09-07',
        'departure_time' => '08:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-07 08:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 240, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-07 12:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::create(['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id, 'base_amount' => 7000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'selling_agency_id' => $agency->id,
        'status' => 'ticketed',
        'total_amount' => 7000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'total_snapshot' => ['passenger_allocations' => [7000]],
        'ticket_issuance_status' => 'issued',
        'ticket_issued_at' => now(),
    ]);

    $passenger = ReservationPassenger::create([
        'reservation_id' => $reservation->id,
        'first_name' => 'Aïcha',
        'last_name' => 'HOUNKPÈ',
        'phone' => '+2290197421860',
        'passenger_index' => 0,
        'status' => 'active',
    ]);

    ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'leg_order' => 1,
        'direction' => 'outbound',
        'passenger_index' => 0,
        'status' => 'ticketed',
    ]);

    Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::Cash,
        'provider' => 'cash',
        'provider_reference' => 'cash-'.uniqid(),
        'idempotency_key' => 'pay-'.uniqid(),
        'amount' => 7000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Confirmed,
        'verified_by' => $user->id,
        'verified_at' => now(),
        'confirmed_at' => now(),
    ]);

    $ticket = Ticket::create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'legacy-uuid-payload',
        'short_code' => 'KAB12CD',
        'status' => TicketStatus::Issued,
        'issued_at' => now(),
    ]);

    return compact('user', 'agency', 'reservation', 'passenger', 'ticket', 'departure', 'origin', 'destination', 'seat');
}

it('renders the ticket view page for an authenticated owner', function (): void {
    $scenario = ticketViewScenario();
    $this->actingAs($scenario['user']);

    $response = $this->get("/tickets/{$scenario['ticket']->uuid}");

    $response->assertOk();
    $response->assertSee('KORA EXPRESS');
    $response->assertSee('Aïcha HOUNKPÈ');
    $response->assertSee('Cotonou');
    $response->assertSee('Djougou');
    $response->assertSee('KAB12CD');
});

it('includes the QR code SVG in the ticket view', function (): void {
    $scenario = ticketViewScenario();
    $this->actingAs($scenario['user']);

    $response = $this->get("/tickets/{$scenario['ticket']->uuid}");

    $response->assertOk();
    expect($response->getContent())->toContain('<svg');
});

it('denies access to tickets not owned by the user', function (): void {
    $scenario = ticketViewScenario();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser);

    $response = $this->get("/tickets/{$scenario['ticket']->uuid}");

    $response->assertForbidden();
});

it('displays all segments for a round-trip ticket', function (): void {
    $scenario = ticketViewScenario();
    $this->actingAs($scenario['user']);

    // Add a return leg (inbound) to the existing passenger.
    $returnOrigin = Stop::create([
        'line_id' => $scenario['departure']->line_id,
        'physical_stop_id' => $scenario['destination']->physical_stop_id,
        'name' => 'Djougou',
        'city' => 'Djougou',
        'country' => 'BJ',
        'stop_order' => 3,
        'status' => 'active',
    ]);
    $returnDestination = Stop::create([
        'line_id' => $scenario['departure']->line_id,
        'physical_stop_id' => $scenario['origin']->physical_stop_id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 4,
        'status' => 'active',
    ]);
    $returnDeparture = Departure::create([
        'line_id' => $scenario['departure']->line_id,
        'bus_id' => $scenario['departure']->bus_id,
        'departure_date' => '2026-09-08',
        'departure_time' => '14:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-08 14:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $returnOrigin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $returnDestination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 240, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-08 18:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    ReservationSegment::create([
        'reservation_id' => $scenario['reservation']->id,
        'reservation_passenger_id' => $scenario['passenger']->id,
        'departure_id' => $returnDeparture->id,
        'seat_id' => $scenario['seat']->id,
        'origin_stop_id' => $returnOrigin->id,
        'destination_stop_id' => $returnDestination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'leg_order' => 2,
        'direction' => 'inbound',
        'passenger_index' => 0,
        'status' => 'ticketed',
    ]);

    $response = $this->get("/tickets/{$scenario['ticket']->uuid}");

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('Aller');
    expect($content)->toContain('Retour');
    expect($content)->toContain('2 jambes');
});
