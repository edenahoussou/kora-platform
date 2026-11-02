<?php

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
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
