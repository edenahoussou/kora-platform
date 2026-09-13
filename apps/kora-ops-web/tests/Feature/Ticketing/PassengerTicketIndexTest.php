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
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerTicketIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_401(): void
    {
        $this->getJson(route('api.v1.tickets.index'))->assertUnauthorized();
    }

    public function test_authenticated_passenger_with_no_tickets_gets_empty_list(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_list_returns_tickets_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $scenario = $this->createTicketScenario(
            user: $user,
            shortCode: 'KE-20260804-78342',
        );
        $token = $user->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.short_code', 'KE-20260804-78342')
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.0.amount', 7000)
            ->assertJsonPath('data.0.currency', 'XOF')
            ->assertJsonPath('data.0.passenger.first_name', 'Aïcha')
            ->assertJsonPath('data.0.passenger.last_name', 'HOUNKPÈ')
            ->assertJsonPath('data.0.origin.city', 'Cotonou')
            ->assertJsonPath('data.0.destination.city', 'Djougou')
            ->assertJsonPath('data.0.seat.code', '12A')
            ->assertJsonPath('data.0.departure.line_name', 'Cotonou-Djougou')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.passenger.id');
    }

    public function test_user_only_sees_their_own_tickets(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->createTicketScenario(user: $userA, shortCode: 'KAAA001');
        $this->createTicketScenario(user: $userB, shortCode: 'KBBB002');

        $tokenA = $userA->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        $this->withToken($tokenA)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.short_code', 'KAAA001');
    }

    public function test_ticket_status_mapping(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        $this->createTicketScenario(user: $user, shortCode: 'KISS001', status: TicketStatus::Issued);
        $this->createTicketScenario(user: $user, shortCode: 'KUSE001', status: TicketStatus::Used);
        $this->createTicketScenario(user: $user, shortCode: 'KCAN001', status: TicketStatus::Cancelled);
        $this->createTicketScenario(user: $user, shortCode: 'KREF001', status: TicketStatus::Refunded);

        $this->withToken($token)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        $statuses = collect(
            $this->withToken($token)->getJson(route('api.v1.tickets.index'))['data']
        )->pluck('status')->unique()->values()->all();

        $this->assertContains('confirmed', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertContains('cancelled', $statuses);
    }

    public function test_tickets_are_paginated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        for ($i = 0; $i < 20; $i++) {
            $this->createTicketScenario(user: $user, shortCode: 'KTEST'.$i);
        }

        $this->withToken($token)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(15, 'data');
    }

    public function test_payer_user_id_also_grants_access(): void
    {
        $creator = User::factory()->create();
        $payer = User::factory()->create();

        $scenario = $this->createTicketScenario(
            user: $creator,
            payer: $payer,
            shortCode: 'KPAY001',
        );

        $tokenPayer = $payer->createToken('test-device', ['*'], now()->addDays(7))->plainTextToken;

        $this->withToken($tokenPayer)
            ->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.short_code', 'KPAY001');
    }

    private function createTicketScenario(
        ?User $user = null,
        ?User $payer = null,
        ?string $shortCode = null,
        string $firstName = 'Aïcha',
        string $lastName = 'HOUNKPÈ',
        TicketStatus $status = TicketStatus::Issued,
    ): array {
        $shortCode = $shortCode ?? 'K'.strtoupper(bin2hex(random_bytes(3)));
        $user = $user ?? User::factory()->create();
        $suffix = strtoupper(bin2hex(random_bytes(2)));

        $originPhysical = PhysicalStop::create([
            'name' => 'Cotonou Dantokpa',
            'code' => 'CTN'.$suffix,
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ]);
        $destinationPhysical = PhysicalStop::create([
            'name' => 'Djougou Centre',
            'code' => 'DJG'.$suffix,
            'city' => 'Djougou',
            'country' => 'BJ',
            'status' => 'active',
        ]);

        $lineCode = 'K'.strtoupper(bin2hex(random_bytes(4)));
        $line = Line::create([
            'code' => $lineCode,
            'name' => 'Cotonou-Djougou',
            'countries_crossed' => ['BJ'],
            'status' => 'active',
        ]);
        $origin = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $originPhysical->id,
            'name' => 'Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'stop_order' => 1,
            'status' => 'active',
        ]);
        $destination = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $destinationPhysical->id,
            'name' => 'Djougou',
            'city' => 'Djougou',
            'country' => 'BJ',
            'stop_order' => 2,
            'status' => 'active',
        ]);

        $bus = Bus::create([
            'registration_number' => 'BJ-'.uniqid(),
            'brand' => 'KORA',
            'model' => 'Coach',
            'seat_count' => 2,
            'status' => 'active',
        ]);
        $seat = Seat::create([
            'bus_id' => $bus->id,
            'code' => '12A',
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);

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

        $reservation = Reservation::create([
            'created_by_user_id' => $user->id,
            'payer_user_id' => $payer?->id,
            'status' => 'ticketed',
            'total_amount' => 7000,
            'currency' => 'XOF',
            'channel' => 'online',
            'total_snapshot' => ['passenger_allocations' => [7000]],
            'ticket_issuance_status' => 'issued',
            'ticket_issued_at' => now(),
        ]);

        $passenger = ReservationPassenger::create([
            'reservation_id' => $reservation->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
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

        $ticket = Ticket::create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'qr_code' => 'test-qr-'.uniqid(),
            'short_code' => $shortCode,
            'status' => $status,
            'issued_at' => now(),
        ]);

        return compact('user', 'reservation', 'passenger', 'ticket', 'departure', 'origin', 'destination', 'seat');
    }
}
