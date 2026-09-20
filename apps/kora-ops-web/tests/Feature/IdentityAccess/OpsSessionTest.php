<?php

use App\Models\User;
use App\Modules\IdentityAccess\Actions\AssignTerminalAction;
use App\Modules\IdentityAccess\Actions\DeactivateTerminalAction;
use App\Modules\IdentityAccess\Actions\ReportTerminalLostAction;
use App\Modules\IdentityAccess\Actions\RevokeTerminalSessionsAction;
use App\Modules\IdentityAccess\Actions\SetTerminalMaintenanceAction;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    // Ces tests portent sur la session elle-même : aucune ouverture automatique.
    $this->autoOpenOpsSession = false;
});

function opsSessionUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array{0: string, 1: int} [jeton en clair, identifiant du jeton] */
function opsSessionToken(User $user): array
{
    $token = $user->createToken('device:field', ['*'], now()->addDays(7));

    return [$token->plainTextToken, $token->accessToken->id];
}

function opsSessionTerminal(?User $assignedTo = null, string $serial = 'P501-SES-001', TerminalP501Status $status = TerminalP501Status::Active): TerminalP501
{
    return TerminalP501::query()->create([
        'serial_number' => $serial,
        'assigned_user_id' => $assignedTo?->id,
        'status' => $status,
    ]);
}

/**
 * Plusieurs requêtes simulées dans un même test réutiliseraient l'utilisateur
 * déjà résolu par le guard Sanctum (et ses rôles en cache) au lieu de
 * revérifier le jeton en base, comme le fait chaque vraie requête : on repart
 * donc d'un guard neuf à chaque appel.
 *
 * @param  array<string, mixed>  $data
 */
function opsSessionCall(mixed $test, string $method, string $uri, string $plainToken, string $serial = '', array $data = []): TestResponse
{
    Auth::forgetGuards();

    return $test->withToken($plainToken)
        ->withHeaders(['X-Terminal-Serial' => $serial])
        ->json($method, $uri, $data);
}

function opsSessionOpen(mixed $test, string $plainToken, TerminalP501 $terminal): TestResponse
{
    return opsSessionCall($test, 'POST', '/api/v1/ops-mobile/terminal/handshake', $plainToken, '', ['serial_number' => $terminal->serial_number]);
}

function opsSessionDepartures(mixed $test, string $plainToken, TerminalP501|string $terminal): TestResponse
{
    $serial = $terminal instanceof TerminalP501 ? $terminal->serial_number : $terminal;

    return opsSessionCall($test, 'GET', '/api/v1/ops/departures', $plainToken, $serial);
}

function opsSessionLatestActivity(string $description): ?Activity
{
    return Activity::query()->where('description', $description)->latest('id')->first();
}

// ---------------------------------------------------------------------------
// Ouverture : un convoyeur habilité, uniquement sur un terminal autorisé
// ---------------------------------------------------------------------------

it('opens a session on the assigned terminal and returns a deterministic state', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);

    opsSessionOpen($this, $plain, $terminal)
        ->assertOk()
        ->assertJsonPath('data.uuid', $terminal->uuid)
        ->assertJsonPath('data.serial_number', 'P501-SES-001')
        ->assertJsonPath('data.session.state', 'active')
        ->assertJsonPath('data.session.renewal_count', 0)
        ->assertJsonPath('data.capabilities.departures_view', true)
        ->assertJsonPath('data.capabilities.boarding_scan', true)
        ->assertJsonPath('data.user.uuid', $user->uuid)
        ->assertJsonStructure(['meta' => ['server_time'], 'data' => ['session' => ['uuid', 'expires_at', 'renewable_until']]])
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.session.id')
        ->assertJsonMissingPath('data.user.id');

    $session = TerminalSession::query()->firstOrFail();

    expect($session->personal_access_token_id)->toBe($tokenId)
        ->and($session->terminal_p501_id)->toBe($terminal->id)
        ->and($session->user_id)->toBe($user->id)
        ->and((int) round($session->started_at->diffInMinutes($session->expires_at)))->toBe(720)
        ->and((int) round($session->started_at->diffInMinutes($session->absolute_expires_at)))->toBe(1440)
        ->and($session->renewal_count)->toBe(0)
        ->and($session->revoked_at)->toBeNull();

    $opened = opsSessionLatestActivity('ops_session_opened');

    expect($opened)->not->toBeNull()
        ->and($opened->causer_id)->toBe($user->id)
        ->and($opened->subject_id)->toBe($terminal->id)
        ->and($opened->properties['session_uuid'])->toBe($session->uuid)
        ->and($opened->properties['serial_number'])->toBe('P501-SES-001')
        ->and(opsSessionLatestActivity('terminal_handshake'))->not->toBeNull();
});

it('refuses an unknown terminal, journalising the refusal with the presented serial', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/terminal/handshake', $plain, '', ['serial_number' => 'INCONNU-999'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown')
        ->assertJsonPath('error.action', 'contact_agency');

    $refusal = opsSessionLatestActivity('ops_session_refused');

    expect(TerminalSession::query()->count())->toBe(0)
        ->and($refusal)->not->toBeNull()
        ->and($refusal->causer_id)->toBe($user->id)
        ->and($refusal->properties['code'])->toBe('terminal_unknown')
        ->and($refusal->properties['serial_number'])->toBe('INCONNU-999');
});

it('refuses inactive, maintenance and lost terminals with distinct codes and actions', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);

    $cases = [
        [TerminalP501Status::Inactive, 'terminal_inactive', 'contact_agency'],
        [TerminalP501Status::Maintenance, 'terminal_maintenance', 'retry_later'],
        [TerminalP501Status::Lost, 'terminal_lost', 'contact_agency'],
    ];

    foreach ($cases as $index => [$status, $code, $action]) {
        $terminal = opsSessionTerminal($user, 'P501-SES-S'.$index, $status);

        opsSessionOpen($this, $plain, $terminal)
            ->assertForbidden()
            ->assertJsonPath('error.code', $code)
            ->assertJsonPath('error.action', $action);
    }

    expect(TerminalSession::query()->count())->toBe(0);
});

it('refuses a terminal assigned to someone else, or to nobody', function (): void {
    $user = opsSessionUser();
    $other = opsSessionUser();
    [$plain] = opsSessionToken($user);

    foreach ([$other, null] as $index => $owner) {
        $terminal = opsSessionTerminal($owner, 'P501-SES-N'.$index);

        opsSessionOpen($this, $plain, $terminal)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'terminal_not_assigned')
            ->assertJsonPath('error.action', 'contact_agency');
    }

    $refusal = opsSessionLatestActivity('ops_session_refused');

    expect(TerminalSession::query()->count())->toBe(0)
        ->and($refusal->properties['code'])->toBe('terminal_not_assigned');
});

it('refuses a user without an OPS role, even holding a valid token and an assigned terminal', function (): void {
    foreach (['passenger', null] as $index => $role) {
        $user = $role === null ? User::factory()->create() : opsSessionUser($role);
        [$plain] = opsSessionToken($user);
        $terminal = opsSessionTerminal($user, 'P501-SES-R'.$index);

        opsSessionOpen($this, $plain, $terminal)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'role_not_authorized')
            ->assertJsonPath('error.action', 'contact_agency');
    }

    expect(TerminalSession::query()->count())->toBe(0);
});

it('refuses a field role that lost the departures.view permission', function (): void {
    Role::findByName('conductor')->revokePermissionTo('departures.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);

    opsSessionOpen($this, $plain, $terminal)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'permission_denied');

    expect(TerminalSession::query()->count())->toBe(0);
});

it('refuses a suspended account and a locked account with distinct codes', function (): void {
    $suspended = opsSessionUser();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();
    [$suspendedToken] = opsSessionToken($suspended);

    opsSessionOpen($this, $suspendedToken, opsSessionTerminal($suspended, 'P501-SES-U1'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_suspended')
        ->assertJsonPath('error.action', 'contact_agency');

    $locked = opsSessionUser();
    $locked->forceFill(['status' => UserStatus::Locked])->save();
    [$lockedToken] = opsSessionToken($locked);

    opsSessionOpen($this, $lockedToken, opsSessionTerminal($locked, 'P501-SES-U2'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_locked')
        ->assertJsonPath('error.action', 'retry_later');
});

it('requires authentication and a token before opening a session', function (): void {
    $user = opsSessionUser();
    $terminal = opsSessionTerminal($user);

    Auth::forgetGuards();
    $this->postJson('/api/v1/ops-mobile/terminal/handshake', ['serial_number' => $terminal->serial_number])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_invalid')
        ->assertJsonPath('error.action', 'reauthenticate');
});

// ---------------------------------------------------------------------------
// Changement de terminal, remplacement de session
// ---------------------------------------------------------------------------

it('binds the session to one terminal: switching terminal replaces it and the old serial is refused', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminalA = opsSessionTerminal($user, 'P501-SES-A');
    $terminalB = opsSessionTerminal($user, 'P501-SES-B');

    opsSessionOpen($this, $plain, $terminalA)->assertOk();
    opsSessionOpen($this, $plain, $terminalB)->assertOk();

    $sessions = TerminalSession::query()->orderBy('id')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->revoked_reason)->toBe('replaced')
        ->and($sessions[1]->revoked_at)->toBeNull()
        // Le jeton qui a servi à ouvrir la seconde session n'est pas supprimé.
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeTrue();

    opsSessionDepartures($this, $plain, $terminalA)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_mismatch')
        ->assertJsonPath('error.action', 'open_session');

    opsSessionDepartures($this, $plain, $terminalB)->assertOk();
});

it('closes the session of another device when the same user opens a session elsewhere', function (): void {
    $user = opsSessionUser();
    [$firstPlain, $firstTokenId] = opsSessionToken($user);
    [$secondPlain] = opsSessionToken($user);
    $terminalA = opsSessionTerminal($user, 'P501-SES-A');
    $terminalB = opsSessionTerminal($user, 'P501-SES-B');

    opsSessionOpen($this, $firstPlain, $terminalA)->assertOk();
    opsSessionOpen($this, $secondPlain, $terminalB)->assertOk();

    expect(PersonalAccessToken::query()->whereKey($firstTokenId)->exists())->toBeFalse();

    opsSessionDepartures($this, $firstPlain, $terminalA)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_revoked')
        ->assertJsonPath('error.reason', 'replaced')
        ->assertJsonPath('error.action', 'reauthenticate');

    opsSessionDepartures($this, $secondPlain, $terminalB)->assertOk();
});

it('invalidates the sessions of the previous holder when the terminal is reassigned', function (): void {
    $admin = opsSessionUser('operations_director');
    $previous = opsSessionUser();
    $next = opsSessionUser();
    [$previousPlain] = opsSessionToken($previous);
    [$nextPlain] = opsSessionToken($next);
    $terminal = opsSessionTerminal($previous);

    opsSessionOpen($this, $previousPlain, $terminal)->assertOk();

    app(AssignTerminalAction::class)->execute($terminal, ['assigned_user_id' => $next->id], $admin);

    opsSessionDepartures($this, $previousPlain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_revoked')
        ->assertJsonPath('error.reason', 'terminal_reassigned');

    opsSessionOpen($this, $nextPlain, $terminal->refresh())->assertOk();
    opsSessionDepartures($this, $nextPlain, $terminal)->assertOk();
});

it('refuses and revokes a session whose terminal was reassigned without going through the assignment action', function (): void {
    $user = opsSessionUser();
    $other = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);

    opsSessionOpen($this, $plain, $terminal)->assertOk();
    $terminal->update(['assigned_user_id' => $other->id]);

    opsSessionDepartures($this, $plain, $terminal)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_not_assigned');

    $session = TerminalSession::query()->firstOrFail();

    expect($session->revoked_reason)->toBe('terminal_reassigned')
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Consultation, renouvellement, déconnexion
// ---------------------------------------------------------------------------

it('exposes the current session state with the server time', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $session = TerminalSession::query()->firstOrFail();

    opsSessionCall($this, 'GET', '/api/v1/ops-mobile/session', $plain, $terminal->serial_number)
        ->assertOk()
        ->assertJsonPath('data.session.uuid', $session->uuid)
        ->assertJsonPath('data.session.state', 'active')
        ->assertJsonPath('data.uuid', $terminal->uuid)
        ->assertJsonPath('data.capabilities.boarding_scan', true)
        ->assertJsonStructure(['meta' => ['server_time']]);
});

it('renews the session window and journalises the renewal', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();
    $openedExpiry = TerminalSession::query()->firstOrFail()->expires_at;

    $this->travel(6)->hours();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)
        ->assertOk()
        ->assertJsonPath('data.session.renewal_count', 1);

    $session = TerminalSession::query()->firstOrFail();

    expect($session->renewal_count)->toBe(1)
        ->and($session->renewed_at)->not->toBeNull()
        ->and($session->expires_at->gt($openedExpiry))->toBeTrue()
        ->and((int) round(now()->diffInMinutes($session->expires_at)))->toBe(720);

    $renewal = opsSessionLatestActivity('ops_session_renewed');

    expect($renewal)->not->toBeNull()
        ->and($renewal->causer_id)->toBe($user->id)
        ->and($renewal->properties['session_uuid'])->toBe($session->uuid)
        ->and($renewal->properties['renewal_count'])->toBe(1);
});

it('never renews beyond the absolute lifetime', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $this->travel(11)->hours();
    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)->assertOk();

    $this->travel(11)->hours();
    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)->assertOk();

    $session = TerminalSession::query()->firstOrFail();

    // 22 h après l'ouverture : la fenêtre (12 h) dépasserait 24 h, elle est plafonnée.
    expect($session->expires_at->equalTo($session->absolute_expires_at))->toBeTrue();

    $this->travel(3)->hours();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired');
});

it('does not renew an expired session: it is closed and the terminal must sign in again', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $this->travel(13)->hours();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired')
        ->assertJsonPath('error.action', 'reauthenticate')
        ->assertJsonPath('error.reason', 'expired');

    $session = TerminalSession::query()->firstOrFail();

    expect($session->revoked_reason)->toBe('expired')
        ->and($session->renewal_count)->toBe(0)
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse()
        ->and(opsSessionLatestActivity('ops_session_expired'))->not->toBeNull();

    // Aucun retour en arrière : même jeton, même terminal, toujours refusé.
    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired');
});

it('refuses to renew while the terminal is in maintenance, without extending the session', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();
    $before = TerminalSession::query()->firstOrFail();

    $terminal->update(['status' => TerminalP501Status::Maintenance]);
    $this->travel(1)->hours();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_maintenance')
        ->assertJsonPath('error.action', 'retry_later');

    $after = TerminalSession::query()->firstOrFail();

    expect($after->expires_at->equalTo($before->expires_at))->toBeTrue()
        ->and($after->renewal_count)->toBe(0)
        ->and($after->revoked_at)->toBeNull();
});

it('closes the session and the token on logout, then refuses the token with its reason', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    opsSessionCall($this, 'DELETE', '/api/v1/ops-mobile/session', $plain, $terminal->serial_number)
        ->assertNoContent();

    $session = TerminalSession::query()->firstOrFail();
    $closed = opsSessionLatestActivity('ops_session_closed');

    expect($session->revoked_reason)->toBe('logout')
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse()
        ->and($closed)->not->toBeNull()
        ->and($closed->causer_id)->toBe($user->id)
        ->and($closed->properties['reason'])->toBe('logout');

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_revoked')
        ->assertJsonPath('error.reason', 'logout');
});

it('lets a convoyeur log out even when the terminal is in maintenance', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $terminal->update(['status' => TerminalP501Status::Maintenance]);

    opsSessionCall($this, 'DELETE', '/api/v1/ops-mobile/session', $plain, $terminal->serial_number)
        ->assertNoContent();

    expect(TerminalSession::query()->firstOrFail()->revoked_reason)->toBe('logout');
});

it('also closes the linked OPS session through the generic logout endpoint', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    opsSessionCall($this, 'POST', '/api/v1/auth/logout', $plain, $terminal->serial_number)
        ->assertNoContent();

    expect(TerminalSession::query()->firstOrFail()->revoked_reason)->toBe('logout')
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Accès aux routes OPS : session obligatoire, rôle, terminal, périmètre
// ---------------------------------------------------------------------------

it('refuses OPS routes for a valid token that never opened a session', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);

    foreach (['GET' => '/api/v1/ops/departures', 'POST' => '/api/v1/ops-mobile/terminal/sync'] as $method => $uri) {
        opsSessionCall($this, $method, $uri, $plain, $terminal->serial_number)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'session_not_opened')
            ->assertJsonPath('error.action', 'open_session');
    }

    $refusal = opsSessionLatestActivity('ops_session_refused');

    expect($refusal)->not->toBeNull()
        ->and($refusal->properties['code'])->toBe('session_not_opened');
});

it('serves OPS routes once the session is open', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    opsSessionDepartures($this, $plain, $terminal)->assertOk();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/terminal/sync', $plain, $terminal->serial_number, ['summary' => ['operations' => 2]])
        ->assertOk()
        ->assertJsonPath('data.serial_number', 'P501-SES-001');
});

it('refuses a session presented with another terminal serial', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    $other = opsSessionTerminal(null, 'P501-SES-OTHER');
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    opsSessionDepartures($this, $plain, $other)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'terminal_mismatch')
        ->assertJsonPath('error.action', 'open_session');

    opsSessionDepartures($this, $plain, 'SERIE-INCONNUE')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown');

    opsSessionDepartures($this, $plain, '')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'terminal_unknown');
});

it('checks the terminal state before the session, even with an open session', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    foreach ([[TerminalP501Status::Lost, 'terminal_lost'], [TerminalP501Status::Inactive, 'terminal_inactive'], [TerminalP501Status::Maintenance, 'terminal_maintenance']] as [$status, $code]) {
        $terminal->update(['status' => $status]);

        opsSessionDepartures($this, $plain, $terminal)
            ->assertForbidden()
            ->assertJsonPath('error.code', $code);
    }
});

it('refuses direct access without an OPS role, whatever the terminal', function (): void {
    $passenger = opsSessionUser('passenger');
    [$plain] = opsSessionToken($passenger);
    $terminal = opsSessionTerminal($passenger);

    foreach (['GET' => '/api/v1/ops/departures', 'POST' => '/api/v1/ops-mobile/terminal/activity'] as $method => $uri) {
        opsSessionCall($this, $method, $uri, $plain, $terminal->serial_number, ['type' => 'scan'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'role_not_authorized');
    }
});

it('refuses a session when a field role loses its OPS role mid-session, and revokes it', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $user->removeRole('conductor');

    opsSessionDepartures($this, $plain, $terminal)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'role_not_authorized');

    expect(TerminalSession::query()->firstOrFail()->revoked_reason)->toBe('access_removed')
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_revoked')
        ->assertJsonPath('error.reason', 'access_removed');
});

it('invalidates the sessions of an account the moment it is suspended', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $user->forceFill(['status' => UserStatus::Suspended])->save();

    // Immédiat : sans attendre la prochaine requête du terminal.
    expect(TerminalSession::query()->firstOrFail()->revoked_reason)->toBe('account_suspended')
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_revoked')
        ->assertJsonPath('error.reason', 'account_suspended')
        ->assertJsonPath('error.action', 'reauthenticate');
});

it('refuses a temporarily locked account without cutting its session', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $user->forceFill(['status' => UserStatus::Locked])->save();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_locked')
        ->assertJsonPath('error.action', 'retry_later');

    expect(TerminalSession::query()->firstOrFail()->revoked_at)->toBeNull();

    $user->forceFill(['status' => UserStatus::Active])->save();

    opsSessionDepartures($this, $plain, $terminal)->assertOk();
});

// ---------------------------------------------------------------------------
// Révocation d'un terminal, expiration, jeton invalide
// ---------------------------------------------------------------------------

it('invalidates the sessions of a terminal put in maintenance, lost or deactivated', function (): void {
    $admin = opsSessionUser('operations_director');

    $cases = [
        ['maintenance', SetTerminalMaintenanceAction::class, 'terminal_maintenance'],
        ['lost', ReportTerminalLostAction::class, 'terminal_lost'],
        ['deactivated', DeactivateTerminalAction::class, 'terminal_deactivated'],
    ];

    foreach ($cases as [$label, $actionClass, $reason]) {
        $user = opsSessionUser();
        [$plain, $tokenId] = opsSessionToken($user);
        $terminal = opsSessionTerminal($user, 'P501-SES-T-'.$label);
        opsSessionOpen($this, $plain, $terminal)->assertOk();

        app($actionClass)->execute($terminal, $admin);

        expect(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse()
            ->and(TerminalSession::query()->where('terminal_p501_id', $terminal->id)->firstOrFail()->revoked_reason)->toBe($reason);

        // Le jeton étant supprimé, le refus reste déterministe : session révoquée + motif.
        opsSessionCall($this, 'POST', '/api/v1/ops-mobile/terminal/sync', $plain, $terminal->serial_number)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'session_revoked')
            ->assertJsonPath('error.reason', $reason);
    }

    $revoked = Activity::query()
        ->where('description', 'ops_session_revoked')
        ->get()
        ->map(fn (Activity $activity): string => $activity->properties['reason'])
        ->all();

    expect($revoked)->toContain('terminal_maintenance', 'terminal_lost', 'terminal_deactivated')
        ->and(opsSessionLatestActivity('terminal_maintenance_set')->properties['revoked_sessions'])->toBe(1);
});

it('revokes the sessions of a terminal through the bulk revocation action', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    expect(app(RevokeTerminalSessionsAction::class)->execute($terminal, 'terminal_lost'))->toBe(1)
        ->and(app(RevokeTerminalSessionsAction::class)->execute($terminal, 'terminal_lost'))->toBe(0);
});

it('answers session_expired once the session window has elapsed, and keeps answering it', function (): void {
    $user = opsSessionUser();
    [$plain, $tokenId] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $this->travel(13)->hours();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired')
        ->assertJsonPath('error.action', 'reauthenticate');

    expect(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();

    opsSessionDepartures($this, $plain, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired');
});

it('answers session_expired for a token expired on the Sanctum side', function (): void {
    $user = opsSessionUser();
    $expired = $user->createToken('device:field', ['*'], now()->subMinute());
    $terminal = opsSessionTerminal($user);

    opsSessionDepartures($this, $expired->plainTextToken, $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired');
});

it('answers session_invalid for an unknown token and keeps the generic 401 outside OPS routes', function (): void {
    $terminal = opsSessionTerminal(null);

    opsSessionDepartures($this, '9999|jeton-inconnu', $terminal)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_invalid')
        ->assertJsonPath('error.action', 'reauthenticate');

    opsSessionCall($this, 'GET', '/api/v1/auth/me', '9999|jeton-inconnu')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.')
        ->assertJsonMissingPath('error');
});

it('never exposes internal numeric identifiers in error payloads', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);

    $response = opsSessionDepartures($this, $plain, $terminal)->assertUnauthorized();

    expect(array_keys($response->json('error')))->toEqualCanonicalizing(['code', 'message', 'action']);

    opsSessionOpen($this, $plain, $terminal)->assertOk();
    $this->travel(13)->hours();

    $expired = opsSessionDepartures($this, $plain, $terminal)->assertUnauthorized();

    expect(array_keys($expired->json('error')))->toEqualCanonicalizing(['code', 'message', 'action', 'reason'])
        ->and($expired->getContent())->not->toContain('"id"');
});

// ---------------------------------------------------------------------------
// Réseau dégradé : reprise contrôlée
// ---------------------------------------------------------------------------

it('resumes after a long network loss only through a new login and a new session', function (): void {
    $user = opsSessionUser();
    [$oldPlain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $oldPlain, $terminal)->assertOk();

    // Le terminal reste hors réseau plus longtemps que la fenêtre de session.
    $this->travel(14)->hours();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/terminal/sync', $oldPlain, $terminal->serial_number, ['summary' => ['operations' => 5]])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired');

    // Reconnexion (nouveau jeton), puis nouveau handshake : la synchronisation reprend.
    [$newPlain] = opsSessionToken($user);
    opsSessionOpen($this, $newPlain, $terminal->refresh())->assertOk();

    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/terminal/sync', $newPlain, $terminal->serial_number, ['summary' => ['operations' => 5]])
        ->assertOk();

    expect(TerminalSession::query()->active()->count())->toBe(1);
});

it('renews on network return when still inside the window, without a new login', function (): void {
    $user = opsSessionUser();
    [$plain] = opsSessionToken($user);
    $terminal = opsSessionTerminal($user);
    opsSessionOpen($this, $plain, $terminal)->assertOk();

    $this->travel(10)->hours();

    opsSessionCall($this, 'GET', '/api/v1/ops-mobile/session', $plain, $terminal->serial_number)->assertOk();
    opsSessionCall($this, 'POST', '/api/v1/ops-mobile/session/renew', $plain, $terminal->serial_number)->assertOk();

    $this->travel(10)->hours();

    opsSessionDepartures($this, $plain, $terminal)->assertOk();
});
