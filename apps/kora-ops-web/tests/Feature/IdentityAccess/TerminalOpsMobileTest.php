<?php

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeTerminalSessionsAction;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function terminalConductorToken(): array
{
    $user = User::factory()->create();
    $user->assignRole('conductor');
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$user, $token->plainTextToken, $token->accessToken->id];
}

it('opens an ops mobile session for an active, registered terminal', function (): void {
    [$user, $plainToken] = terminalConductorToken();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-100001']);

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'p501-100001'])
        ->assertOk()
        ->assertJsonPath('data.uuid', $terminal->uuid)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonMissingPath('data.id');

    expect($terminal->refresh()->last_handshake_at)->not->toBeNull()
        ->and(TerminalSession::query()->where('terminal_p501_id', $terminal->id)->where('user_id', $user->id)->exists())->toBeTrue();

    $activity = Activity::query()->where('description', 'terminal_handshake')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($terminal->id)
        ->and($activity->properties['serial_number'])->toBe('P501-100001');
});

it('rejects an unknown terminal serial with a functional code', function (): void {
    [, $plainToken] = terminalConductorToken();

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'UNKNOWN-999'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown');
});

it('rejects handshake for lost, inactive or maintenance terminals with distinct codes', function (): void {
    [, $plainToken] = terminalConductorToken();

    $cases = [
        [TerminalP501Status::Lost, 'terminal_lost'],
        [TerminalP501Status::Inactive, 'terminal_inactive'],
        [TerminalP501Status::Maintenance, 'terminal_maintenance'],
    ];

    foreach ($cases as [$status, $expectedCode]) {
        $terminal = TerminalP501::query()->create([
            'serial_number' => 'P501-'.$status->value,
            'status' => $status,
        ]);

        $this->withToken($plainToken)
            ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => $terminal->serial_number])
            ->assertForbidden()
            ->assertJsonPath('error.code', $expectedCode);
    }
});

it('requires authentication before accepting a handshake', function (): void {
    TerminalP501::query()->create(['serial_number' => 'P501-100002']);

    $this->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'P501-100002'])
        ->assertUnauthorized();
});

it('records a successful sync and updates last_sync_at without touching last_handshake_at', function (): void {
    [$user, $plainToken] = terminalConductorToken();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-100003']);

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/sync', ['serial_number' => 'P501-100003', 'summary' => ['operations' => 3]])
        ->assertOk()
        ->assertJsonPath('data.serial_number', 'P501-100003');

    expect($terminal->refresh()->last_sync_at)->not->toBeNull()
        ->and($terminal->last_handshake_at)->toBeNull();

    $activity = Activity::query()->where('description', 'terminal_sync')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($terminal->id)
        ->and($activity->properties['operations'])->toBe(3);
});

it('blocks sync and activity logging once the terminal is inactive', function (): void {
    [, $plainToken] = terminalConductorToken();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-100004', 'status' => TerminalP501Status::Inactive]);

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/sync', ['serial_number' => 'P501-100004'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_inactive');

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/activity', [
            'serial_number' => 'P501-100004',
            'type' => 'scan',
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_inactive');
});

it('logs a scan activity for the terminal', function (): void {
    [$user, $plainToken] = terminalConductorToken();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-100005']);
    $ticketUuid = (string) Str::uuid();

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/activity', [
            'serial_number' => 'P501-100005',
            'type' => 'scan',
            'context' => ['ticket_uuid' => $ticketUuid],
        ])
        ->assertNoContent();

    $activity = Activity::query()->where('description', 'terminal_scan')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($terminal->id)
        ->and($activity->properties['ticket_uuid'])->toBe($ticketUuid)
        ->and($activity->properties['serial_number'])->toBe('P501-100005');
});

it('logs print and error activity types for the terminal', function (): void {
    [, $plainToken] = terminalConductorToken();
    TerminalP501::query()->create(['serial_number' => 'P501-100007']);

    foreach (['print', 'error'] as $type) {
        $this->withToken($plainToken)
            ->postJson('/api/v1/ops-mobile/terminal/activity', [
                'serial_number' => 'P501-100007',
                'type' => $type,
            ])
            ->assertNoContent();
    }

    expect(Activity::query()->where('description', 'terminal_print')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'terminal_error')->exists())->toBeTrue();
});

it('rejects sync and handshake as generic activity types, since each has its own dedicated endpoint', function (): void {
    [, $plainToken] = terminalConductorToken();
    TerminalP501::query()->create(['serial_number' => 'P501-100008']);

    foreach (['sync', 'handshake'] as $type) {
        $this->withToken($plainToken)
            ->postJson('/api/v1/ops-mobile/terminal/activity', [
                'serial_number' => 'P501-100008',
                'type' => $type,
            ])
            ->assertUnprocessable();
    }
});

it('blocks every subsequent operation once the terminal has been reported lost mid-session', function (): void {
    [$user, $plainToken] = terminalConductorToken();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-100006']);

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => 'P501-100006'])
        ->assertOk();

    $terminal->update(['status' => TerminalP501Status::Lost]);

    app(RevokeTerminalSessionsAction::class)
        ->execute($terminal, 'terminal_lost');

    // Le guard 'sanctum' met en cache l'utilisateur résolu sur son
    // instance ; sans ce reset, le 2e appel HTTP de ce test réutiliserait
    // l'authentification du 1er au lieu de revérifier le jeton (qu'on
    // vient de supprimer) contre la base — artefact propre à l'exécution
    // de plusieurs requêtes simulées dans un même test, absent en prod où
    // chaque requête réelle repart d'un guard neuf.
    Auth::forgetGuards();

    $this->withToken($plainToken)
        ->postJson('/api/v1/ops-mobile/terminal/sync', ['serial_number' => 'P501-100006'])
        ->assertUnauthorized();
});
