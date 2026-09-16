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
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

it('requires authentication to download a ticket PDF', function (): void {
    $scenario = ticketPdfScenario();

    $this->getJson(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertUnauthorized();
});

it('returns the exact generated PDF with mobile download headers', function (): void {
    $scenario = ticketPdfScenario();
    $content = '%PDF-KORA ticket';

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Ticket $ticket): bool => $ticket->is($scenario['ticket']))
        ->andReturn([
            'content' => $content,
            'filename' => 'billet-KAB12CD.pdf',
        ]);

    $url = route('api.v1.tickets.pdf', $scenario['ticket']);
    $response = $this->withToken(ticketPdfToken($scenario['user']))->get($url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="billet-KAB12CD.pdf"')
        ->assertHeader('Content-Length', (string) strlen($content))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->getContent())->toBe($content)
        ->and($url)->toContain($scenario['ticket']->uuid)
        ->not->toContain('/'.$scenario['ticket']->getKey().'/')
        ->and($response->getContent())->not->toContain((string) $scenario['ticket']->getKey());
});

it('allows the payer to download the ticket PDF', function (): void {
    $payer = User::factory()->create();
    $scenario = ticketPdfScenario(payer: $payer);

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Ticket $ticket): bool => $ticket->is($scenario['ticket']))
        ->andReturn(['content' => '%PDF-payer', 'filename' => 'billet-KAB12CD.pdf']);

    $this->withToken(ticketPdfToken($payer))
        ->get(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertOk();
});

it('denies a third party without invoking PDF generation', function (): void {
    $scenario = ticketPdfScenario();
    $otherUser = User::factory()->create();

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->never();

    $this->withToken(ticketPdfToken($otherUser))
        ->getJson(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertForbidden()
        ->assertJsonPath('message', 'Vous n’avez pas accès à ce billet.');
});

it('returns not found for an unknown ticket UUID', function (): void {
    $user = User::factory()->create();

    $this->withToken(ticketPdfToken($user))
        ->getJson(route('api.v1.tickets.pdf', ['ticket' => (string) Str::uuid()]))
        ->assertNotFound();
});

it('allows issued and used tickets to be downloaded', function (TicketStatus $status): void {
    $scenario = ticketPdfScenario(status: $status);

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(['content' => '%PDF-status', 'filename' => 'billet-KAB12CD.pdf']);

    $this->withToken(ticketPdfToken($scenario['user']))
        ->get(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertOk();
})->with([
    'issued' => TicketStatus::Issued,
    'used' => TicketStatus::Used,
]);

it('rejects cancelled and refunded tickets', function (TicketStatus $status): void {
    $scenario = ticketPdfScenario(status: $status);

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->never();

    $this->withToken(ticketPdfToken($scenario['user']))
        ->getJson(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'ticket_pdf_unavailable')
        ->assertJsonPath('error.message', "Le PDF de ce billet n'est pas disponible.");
})->with([
    'cancelled' => TicketStatus::Cancelled,
    'refunded' => TicketStatus::Refunded,
]);

it('rejects a ticket without an issuance timestamp', function (): void {
    $scenario = ticketPdfScenario();
    $scenario['ticket']->update(['issued_at' => null]);

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->never();

    $this->withToken(ticketPdfToken($scenario['user']))
        ->getJson(route('api.v1.tickets.pdf', $scenario['ticket']))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'ticket_pdf_unavailable');
});

it('returns a stable error when PDF generation fails', function (): void {
    $scenario = ticketPdfScenario();

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Internal PDF renderer failed with a private filesystem path.'));

    $response = $this->withToken(ticketPdfToken($scenario['user']))
        ->getJson(route('api.v1.tickets.pdf', $scenario['ticket']));

    $response->assertServerError()
        ->assertJsonPath('error.code', 'ticket_pdf_generation_failed')
        ->assertJsonPath('error.message', "Le PDF du billet n'a pas pu être généré.")
        ->assertJsonMissingPath('error.exception');

    expect($response->getContent())->not->toContain('Internal PDF renderer')
        ->not->toContain('private filesystem path');
});

it('generates a PDF with the configured Chromium environment', function (): void {
    $scenario = ticketPdfScenario();
    $previousHome = getenv('HOME');
    putenv('HOME=/var/www');

    try {
        $result = app(GenerateTicketPdfAction::class)->execute($scenario['ticket']);
    } finally {
        if (is_string($previousHome)) {
            putenv("HOME={$previousHome}");
        } else {
            putenv('HOME');
        }
    }

    expect($result['content'])->toStartWith('%PDF-')
        ->and($result['filename'])->toBe('billet-KAB12CD.pdf')
        ->and(getenv('HOME'))->toBe($previousHome);
});

it('passes multi-segment ticket data to the shared PDF generator', function (): void {
    $scenario = ticketPdfScenario(roundTrip: true);

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function (Ticket $ticket) use ($scenario): bool {
            return $ticket->is($scenario['ticket'])
                && $ticket->qr_code === 'signed-qr-payload'
                && $ticket->reservation->total_amount === 7000
                && $ticket->reservation->currency === 'XOF'
                && $ticket->reservationPassenger->segments->count() === 2;
        })
        ->andReturn(['content' => '%PDF-multi-segment', 'filename' => 'billet-KAB12CD.pdf']);

    $response = $this->withToken(ticketPdfToken($scenario['user']))
        ->get(route('api.v1.tickets.pdf', $scenario['ticket']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="billet-KAB12CD.pdf"');

    expect($response->getContent())->toStartWith('%PDF-');
});

function ticketPdfToken(User $user): string
{
    return $user->createToken('ticket-pdf-test-device', ['*'], now()->addDays(7))->plainTextToken;
}

/**
 * @return array{user: User, ticket: Ticket, reservation: Reservation, passenger: ReservationPassenger, departure: Departure, origin: Stop, destination: Stop, seat: Seat}
 */
function ticketPdfScenario(
    ?User $user = null,
    ?User $payer = null,
    TicketStatus $status = TicketStatus::Issued,
    bool $roundTrip = false,
): array {
    $user ??= User::factory()->create();
    $suffix = Str::upper(Str::random(8));

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

    $line = Line::create([
        'code' => 'L'.$suffix,
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
        'registration_number' => 'BJ-'.$suffix,
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

    $ticket = Ticket::create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'signed-qr-payload',
        'short_code' => 'KAB12CD',
        'status' => $status,
        'issued_at' => now(),
    ]);

    if ($roundTrip) {
        $returnOrigin = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $destinationPhysical->id,
            'name' => 'Djougou',
            'city' => 'Djougou',
            'country' => 'BJ',
            'stop_order' => 3,
            'status' => 'active',
        ]);
        $returnDestination = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $originPhysical->id,
            'name' => 'Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'stop_order' => 4,
            'status' => 'active',
        ]);
        $returnSeat = Seat::create([
            'bus_id' => $bus->id,
            'code' => '12B',
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);
        $returnDeparture = Departure::create([
            'line_id' => $line->id,
            'bus_id' => $bus->id,
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
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'departure_id' => $returnDeparture->id,
            'seat_id' => $returnSeat->id,
            'origin_stop_id' => $returnOrigin->id,
            'destination_stop_id' => $returnDestination->id,
            'origin_stop_order' => 3,
            'destination_stop_order' => 4,
            'leg_order' => 2,
            'direction' => 'inbound',
            'passenger_index' => 0,
            'status' => 'ticketed',
        ]);
    }

    return compact('user', 'ticket', 'reservation', 'passenger', 'departure', 'origin', 'destination', 'seat');
}
