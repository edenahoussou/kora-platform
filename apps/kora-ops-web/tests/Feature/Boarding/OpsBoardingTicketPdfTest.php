<?php

use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

/**
 * @return array{ticket: Ticket, departure: Departure, terminal: TerminalP501, token: string}
 */
function ticketPdfBoardingScenario(string $role = 'conductor', string $crewKey = 'conductor_id'): array
{
    [$user, $token] = boardingCrewToken($role);
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, [$crewKey => $user->id]);
    $terminal = boardingTerminal();

    [$ticket] = boardingTicketedPassenger([
        [$departure, $origin, $destination, $seat, 'ticketed'],
    ]);

    return compact('ticket', 'departure', 'terminal', 'token');
}

it('returns the confirmation PDF without exposing the amount, for a crew member assigned to the departure', function (): void {
    $scenario = ticketPdfBoardingScenario();
    $content = '%PDF-KORA confirmation';

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Ticket $ticket, bool $hideAmount): bool => $ticket->is($scenario['ticket']) && $hideAmount === true)
        ->andReturn(['content' => $content, 'filename' => 'billet-KAB12CD.pdf']);

    $this->withToken($scenario['token'])
        ->withHeader('X-Terminal-Serial', $scenario['terminal']->serial_number)
        ->get(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $scenario['ticket']]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="billet-KAB12CD.pdf"');
});

it('denies a crew member not assigned to the departure, without invoking PDF generation', function (): void {
    $scenario = ticketPdfBoardingScenario();
    [$otherUser, $otherToken] = boardingCrewToken();

    $this->mock(GenerateTicketPdfAction::class)->shouldReceive('execute')->never();

    $this->withToken($otherToken)
        ->withHeader('X-Terminal-Serial', $scenario['terminal']->serial_number)
        ->getJson(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $scenario['ticket']]))
        ->assertForbidden();
});

it('denies a role without boarding.scan even if assigned to the departure', function (): void {
    // Le mécanicien est un rôle d'équipage affecté au départ, mais sans
    // la permission boarding.scan (voir CrewDepartureScope::canScanBoarding).
    $scenario = ticketPdfBoardingScenario(role: 'mechanic', crewKey: 'mechanic_id');

    $this->mock(GenerateTicketPdfAction::class)->shouldReceive('execute')->never();

    $this->withToken($scenario['token'])
        ->withHeader('X-Terminal-Serial', $scenario['terminal']->serial_number)
        ->getJson(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $scenario['ticket']]))
        ->assertForbidden();
});

it('rejects a ticket that does not belong to the requested departure', function (): void {
    $scenario = ticketPdfBoardingScenario();

    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $otherDeparture = boardingDeparture($line, $bus, ['conductor_id' => $scenario['departure']->conductor_id]);
    [$otherTicket] = boardingTicketedPassenger([
        [$otherDeparture, $origin, $destination, $seat, 'ticketed'],
    ]);

    $this->mock(GenerateTicketPdfAction::class)->shouldReceive('execute')->never();

    $this->withToken($scenario['token'])
        ->withHeader('X-Terminal-Serial', $scenario['terminal']->serial_number)
        ->getJson(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $otherTicket]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'ticket_not_for_departure');
});

it('requires a registered, active terminal', function (): void {
    $scenario = ticketPdfBoardingScenario();

    $this->mock(GenerateTicketPdfAction::class)->shouldReceive('execute')->never();

    $this->withToken($scenario['token'])
        ->withHeader('X-Terminal-Serial', 'UNKNOWN-SERIAL')
        ->getJson(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $scenario['ticket']]))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown');
});

it('returns a stable, non-leaking error when PDF generation fails', function (): void {
    $scenario = ticketPdfBoardingScenario();

    $this->mock(GenerateTicketPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Internal PDF renderer failed with a private filesystem path.'));

    $response = $this->withToken($scenario['token'])
        ->withHeader('X-Terminal-Serial', $scenario['terminal']->serial_number)
        ->getJson(route('api.v1.ops.boarding.ticket-pdf', [$scenario['departure'], $scenario['ticket']]));

    $response->assertServerError()
        ->assertJsonPath('error.code', 'ticket_pdf_generation_failed');

    expect($response->getContent())->not->toContain('Internal PDF renderer')
        ->not->toContain('private filesystem path');
});
