<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

// Nommée différemment de operationsDirector() (déjà déclarée ailleurs dans
// la suite, ex. BusAmenityManagementTest.php) pour éviter toute collision
// de nom dans l'espace de fonctions global partagé entre fichiers Pest.
function terminalOperationsDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

function terminalConductorUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('conductor');

    return $user;
}

function terminalAgencyManagerViewOnly(): User
{
    $user = User::factory()->create();
    $user->assignRole('agency_manager');

    return $user;
}

it('lists and registers terminals without exposing numeric ids', function (): void {
    $admin = terminalOperationsDirector();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000001']);

    $this->actingAs($admin)
        ->getJson(route('terminals.index'))
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $terminal->uuid)
        ->assertJsonMissingPath('data.0.id');

    $this->actingAs($admin)
        ->postJson(route('terminals.store'), ['serial_number' => 'p501-000002'])
        ->assertCreated()
        ->assertJsonPath('data.serial_number', 'P501-000002')
        ->assertJsonPath('data.status', 'active');

    expect(Activity::query()->where('description', 'terminal_registered')->where('causer_id', $admin->id)->exists())->toBeTrue();
});

it('lets a view-only role see terminals but blocks every mutating action', function (): void {
    $viewer = terminalAgencyManagerViewOnly();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000008']);

    $this->actingAs($viewer)
        ->getJson(route('terminals.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson(route('terminals.show', $terminal))
        ->assertOk();

    $this->actingAs($viewer)
        ->postJson(route('terminals.store'), ['serial_number' => 'P501-000009'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('terminals.activate', $terminal))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('terminals.deactivate', $terminal))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('terminals.maintenance', $terminal))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('terminals.lost', $terminal))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('terminals.assign', $terminal), ['assigned_user_id' => terminalConductorUser()->id])
        ->assertForbidden();

    expect($terminal->refresh()->status)->toBe(TerminalP501Status::Active);
});

it('denies terminal management to users without the permission', function (): void {
    $conductor = terminalConductorUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000003']);

    $this->actingAs($conductor)
        ->getJson(route('terminals.index'))
        ->assertForbidden();

    $this->actingAs($conductor)
        ->postJson(route('terminals.activate', $terminal))
        ->assertForbidden();
});

it('assigns a terminal to a field user and an agency, keeping history', function (): void {
    $admin = terminalOperationsDirector();
    $conductor = terminalConductorUser();
    $agency = Agency::factory()->create();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000004']);

    $this->actingAs($admin)
        ->postJson(route('terminals.assign', $terminal), [
            'assigned_user_id' => $conductor->id,
            'agency_id' => $agency->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_user.uuid', $conductor->uuid);

    expect($terminal->refresh()->assigned_user_id)->toBe($conductor->id)
        ->and(TerminalAssignment::query()->where('terminal_p501_id', $terminal->id)->count())->toBe(1);

    $otherAgent = terminalConductorUser();

    $this->actingAs($admin)
        ->postJson(route('terminals.assign', $terminal), ['assigned_user_id' => $otherAgent->id]);

    $assignments = TerminalAssignment::query()->where('terminal_p501_id', $terminal->id)->orderBy('id')->get();

    expect($assignments)->toHaveCount(2)
        ->and($assignments->first()->unassigned_at)->not->toBeNull()
        ->and($assignments->last()->unassigned_at)->toBeNull()
        ->and($terminal->refresh()->assigned_user_id)->toBe($otherAgent->id);
});

it('revokes active sanctum sessions when a terminal is reported lost', function (): void {
    $admin = terminalOperationsDirector();
    $conductor = terminalConductorUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000005']);

    $token = $conductor->createToken('device:field', ['*'], now()->addDays(7));

    TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id,
        'user_id' => $conductor->id,
        'personal_access_token_id' => $token->accessToken->id,
        'started_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('terminals.lost', $terminal))
        ->assertOk()
        ->assertJsonPath('data.status', 'lost');

    expect(PersonalAccessToken::query()->where('id', $token->accessToken->id)->exists())->toBeFalse()
        ->and(TerminalSession::query()->whereNull('revoked_at')->count())->toBe(0);

    $activity = Activity::query()->where('description', 'terminal_lost')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($terminal->id)
        ->and($activity->properties['revoked_sessions'])->toBe(1);
});
it('revokes active sanctum sessions when a terminal is deactivated', function (): void {
    $admin = terminalOperationsDirector();
    $conductor = terminalConductorUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000006']);

    $token = $conductor->createToken('device:field', ['*'], now()->addDays(7));

    TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id,
        'user_id' => $conductor->id,
        'personal_access_token_id' => $token->accessToken->id,
        'started_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('terminals.deactivate', $terminal))
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect(PersonalAccessToken::query()->where('id', $token->accessToken->id)->exists())->toBeFalse();

    expect(Activity::query()->where('description', 'terminal_deactivated')->where('subject_id', $terminal->id)->exists())->toBeTrue();
});

it('shows terminal detail with assignment history', function (): void {
    $admin = terminalOperationsDirector();
    $conductor = terminalConductorUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-000007', 'status' => TerminalP501Status::Maintenance]);

    $this->actingAs($admin)
        ->postJson(route('terminals.assign', $terminal), ['assigned_user_id' => $conductor->id]);

    $this->actingAs($admin)
        ->getJson(route('terminals.show', $terminal))
        ->assertOk()
        ->assertJsonPath('data.status', 'maintenance')
        ->assertJsonPath('assignment_history.0.assigned_user.uuid', $conductor->uuid);
});
