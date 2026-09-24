<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function terminalManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

it('renders the P501 administration page without exposing phone terminals or numeric ids', function (): void {
    $admin = terminalManagementAdmin();
    $agency = Agency::factory()->create();
    $conductor = User::factory()->create(['agency_id' => $agency->id]);
    $conductor->assignRole('conductor');
    $p501 = TerminalP501::query()->create(['serial_number' => 'P501-WEB-0001']);
    TerminalP501::query()->create([
        'serial_number' => 'TEL-WEB-0001',
        'type' => TerminalType::Phone,
    ]);

    $this->actingAs($admin)
        ->get(route('terminals'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('terminals')
            ->where('terminals.total', 1)
            ->where('terminals.data.0.uuid', $p501->uuid)
            ->missing('terminals.data.0.id')
            ->where('users.0.uuid', $conductor->uuid)
            ->where('users.0.agency.name', $agency->name)
            ->where('users.0.role.code', 'conductor')
            ->where('users.0.role.label', 'Convoyeur')
            ->where('users.0.role.category', 'terrain')
            ->missing('users.0.id')
            ->missing('users.0.role.id')
            ->where('statistics.total', 1)
            ->where('permissions.canManage', true),
        );
});

it('renders the terminal detail page with health, sessions and a unified history', function (): void {
    $admin = terminalManagementAdmin();
    $conductor = User::factory()->create();
    $conductor->assignRole('conductor');
    $agency = Agency::factory()->create();
    $terminal = TerminalP501::query()->create([
        'serial_number' => 'P501-WEB-0002',
        'assigned_user_id' => $conductor->id,
        'agency_id' => $agency->id,
    ]);
    TerminalAssignment::query()->create([
        'terminal_p501_id' => $terminal->id,
        'assigned_user_id' => $conductor->id,
        'agency_id' => $agency->id,
        'assigned_by' => $admin->id,
        'assigned_at' => now()->subHour(),
    ]);
    TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id,
        'user_id' => $conductor->id,
        'started_at' => now()->subMinutes(10),
        'last_seen_at' => now()->subMinutes(5),
        'expires_at' => now()->addHour(),
        'absolute_expires_at' => now()->addHours(4),
    ]);
    activity()
        ->performedOn($terminal)
        ->causedBy($conductor)
        ->withProperties(['internal_id' => 42, 'session_uuid' => 'secret-session', 'label' => 'scan ok'])
        ->log('terminal_scan');

    $this->actingAs($admin)
        ->get(route('terminals.show', $terminal))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('terminals/detail')
            ->where('terminal.uuid', $terminal->uuid)
            ->where('terminal.connectivity_status', 'online')
            ->where('sessions.0.user.uuid', $conductor->uuid)
            ->where('history.data.0.type', 'terminal_scan')
            ->where('history.data.0.actor.uuid', $conductor->uuid)
            ->where('history.data.0.metadata.label', 'scan ok')
            ->missing('history.data.0.metadata.internal_id')
            ->missing('history.data.0.metadata.session_uuid'),
        );
});

it('supports explicit unassignment, stock return and lost terminal recovery', function (): void {
    $admin = terminalManagementAdmin();
    $conductor = User::factory()->create();
    $conductor->assignRole('conductor');
    $terminal = TerminalP501::query()->create([
        'serial_number' => 'P501-WEB-0003',
        'assigned_user_id' => $conductor->id,
    ]);

    TerminalAssignment::query()->create([
        'terminal_p501_id' => $terminal->id,
        'assigned_user_id' => $conductor->id,
        'assigned_by' => $admin->id,
        'assigned_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('terminals.unassign', $terminal), ['reason' => 'Fin de mission'])
        ->assertSuccessful();

    expect($terminal->refresh()->assigned_user_id)->toBeNull()
        ->and(TerminalAssignment::query()->where('terminal_p501_id', $terminal->id)->whereNull('unassigned_at')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->postJson(route('terminals.stock', $terminal), ['reason' => 'Retour dépôt'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', TerminalP501Status::Inactive->value);

    $terminal->update(['status' => TerminalP501Status::Lost]);

    $this->actingAs($admin)
        ->postJson(route('terminals.activate', $terminal))
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson(route('terminals.recover', $terminal), ['reason' => 'Terminal retrouvé'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', TerminalP501Status::Active->value);

    expect(Activity::query()->where('description', 'terminal_unassigned')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'terminal_returned_to_stock')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'terminal_recovered')->exists())->toBeTrue();
});
