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
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('tickets'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_tickets_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('tickets'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->has('tickets')
            ->has('statuses')
            ->has('channels')
            ->has('routes')
            ->has('kpis')
            ->where('kpis.total', 0)
            ->where('kpis.issued', 0)
            ->where('kpis.totalRevenue', 0)
        );
    }

    public function test_tickets_list_shows_ticket_data(): void
    {
        $scenario = $this->createTicketScenario(shortCode: 'KAB12CD');
        $this->actingAs($scenario['user']);

        $response = $this->get(route('tickets'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.shortCode', 'KAB12CD')
            ->where('tickets.data.0.passengerName', 'Aïcha HOUNKPÈ')
            ->where('tickets.data.0.passengerPhone', '+2290197421860')
            ->where('tickets.data.0.originCity', 'Cotonou')
            ->where('tickets.data.0.destinationCity', 'Djougou')
            ->where('tickets.data.0.route', 'Cotonou → Djougou')
            ->where('tickets.data.0.amountLabel', '7 000 XOF')
            ->where('kpis.total', 1)
            ->where('kpis.issued', 1)
            ->where('kpis.totalRevenue', 7000)
            ->has('routes')
        );
    }

    public function test_search_filters_by_short_code(): void
    {
        $scenarioA = $this->createTicketScenario(shortCode: 'KAAA111');
        $scenarioB = $this->createTicketScenario(shortCode: 'KBBB222');
        $this->actingAs($scenarioA['user']);

        $response = $this->get('/tickets?q=KAAA111');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->where('tickets.total', 1)
            ->where('tickets.data.0.shortCode', 'KAAA111')
        );
    }

    public function test_search_filters_by_passenger_name(): void
    {
        $scenarioA = $this->createTicketScenario(firstName: 'Mariam', lastName: 'DIALLO');
        $scenarioB = $this->createTicketScenario(firstName: 'Brice', lastName: 'HOUNKPATIN');
        $this->actingAs($scenarioA['user']);

        $response = $this->get('/tickets?q=DIALLO');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->where('tickets.total', 1)
            ->where('tickets.data.0.passengerName', 'Mariam DIALLO')
        );
    }

    public function test_filter_by_status(): void
    {
        $scenarioIssued = $this->createTicketScenario(status: TicketStatus::Issued, shortCode: 'KIIS001');
        $scenarioUsed = $this->createTicketScenario(status: TicketStatus::Used, shortCode: 'KUSE001');
        $this->actingAs($scenarioIssued['user']);

        $response = $this->get('/tickets?status=used');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->where('tickets.total', 1)
            ->where('tickets.data.0.shortCode', 'KUSE001')
        );
    }

    public function test_filter_by_channel(): void
    {
        $scenarioCounter = $this->createTicketScenario(channel: 'counter', shortCode: 'KCNT01');
        $scenarioOnline = $this->createTicketScenario(channel: 'online', shortCode: 'KONL01');
        $this->actingAs($scenarioCounter['user']);

        $response = $this->get('/tickets?channel=online');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->where('tickets.total', 1)
            ->where('tickets.data.0.shortCode', 'KONL01')
        );
    }

    public function test_tickets_are_paginated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 20; $i++) {
            $this->createTicketScenario(
                user: $user,
                shortCode: 'KTEST'.$i,
            );
        }

        $response = $this->get('/tickets');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('tickets')
            ->where('tickets.per_page', 15)
            ->where('tickets.total', 20)
        );
    }

    private function createTicketScenario(
        ?User $user = null,
        ?string $shortCode = null,
        string $firstName = 'Aïcha',
        string $lastName = 'HOUNKPÈ',
        TicketStatus $status = TicketStatus::Issued,
        string $channel = 'counter',
    ): array {
        $shortCode = $shortCode ?? 'K'.strtoupper(bin2hex(random_bytes(3)));
        $user = $user ?? User::factory()->create();
        $agencyCode = 'COT'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $agency = Agency::create(['code' => $agencyCode, 'name' => 'Cotonou Agence', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
        $user->agency()->associate($agency);
        $user->save();

        $originPhysical = PhysicalStop::create(['name' => 'Cotonou Dantokpa', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
        $destinationPhysical = PhysicalStop::create(['name' => 'Djougou Centre', 'city' => 'Djougou', 'country' => 'BJ', 'status' => 'active']);

        $lineCode = 'K'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $line = Line::create(['code' => $lineCode, 'name' => 'Cotonou-Djougou', 'countries_crossed' => ['BJ'], 'status' => 'active']);
        $origin = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
        $destination = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Djougou', 'city' => 'Djougou', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

        $bus = Bus::create(['registration_number' => 'BJ-'.uniqid(), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 2, 'status' => 'active']);
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

        $reservation = Reservation::create([
            'created_by_user_id' => $user->id,
            'status' => 'ticketed',
            'total_amount' => 7000,
            'currency' => 'XOF',
            'channel' => $channel,
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

        return compact('user', 'agency', 'reservation', 'passenger', 'ticket', 'departure', 'origin', 'destination', 'seat');
    }
}
