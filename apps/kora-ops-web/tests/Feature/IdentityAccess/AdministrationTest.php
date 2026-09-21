<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Services\OpsSessionGuard;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function administrationUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

it('protects administration pages with dedicated permissions', function (): void {
    $user = User::factory()->create();
    $user->assignRole('agency_agent');

    $this->actingAs($user)->get(route('users'))->assertForbidden();
    $this->actingAs($user)->get(route('roles'))->assertForbidden();
});

it('lists users with filters and public identifiers only', function (): void {
    $admin = administrationUser();
    $target = User::factory()->create(['name' => 'Awa Administration']);
    $target->assignRole('operations_director');

    $this->actingAs($admin)
        ->get(route('users', ['q' => 'Awa']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users')
            ->where('users.total', 1)
            ->where('users.data.0.uuid', $target->uuid)
            ->where('users.data.0.role.code', 'operations_director')
            ->missing('users.data.0.id')
            ->missing('users.data.0.role.id')
            ->where('permissions.canManage', true),
        );
});

it('creates an agency user with a temporary password and registration otp', function (): void {
    Notification::fake();
    $admin = administrationUser();
    $agency = Agency::query()->create([
        'code' => 'COT',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Agent Créé',
        'email' => 'agent.created@example.com',
        'phone' => '+22997000001',
        'country_code' => 'BJ',
        'birth_date' => '1990-01-01',
        'status' => 'active',
        'agency_uuid' => $agency->uuid,
        'role' => 'agency_agent',
    ]);

    $response->assertRedirect(route('users', absolute: false));

    $user = User::query()->where('email', 'agent.created@example.com')->firstOrFail();
    $temporaryPassword = session()->get('inertia.flash_data')['temporaryPassword'] ?? null;

    expect($user->hasRole('agency_agent'))->toBeTrue()
        ->and($user->agency_id)->toBe($agency->id)
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check((string) $temporaryPassword, $user->password))->toBeTrue();

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

it('rejects an agency role without an agency', function (): void {
    $admin = administrationUser();

    $this->from(route('users'))
        ->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Agent Sans Agence',
            'email' => 'agent.no-agency@example.com',
            'status' => 'active',
            'role' => 'agency_agent',
        ])
        ->assertRedirect(route('users', absolute: false))
        ->assertSessionHasErrors('agency_uuid');
});

it('archives and restores a user without deleting the record', function (): void {
    $admin = administrationUser();
    $target = User::factory()->create();
    $target->assignRole('agency_agent');
    $device = $target->devices()->create([
        'name' => 'P501',
        'platform' => 'android',
        'device_fingerprint_hash' => hash('sha256', 'device'),
        'status' => UserDeviceStatus::Active,
    ]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect();

    expect($target->refresh()->trashed())->toBeTrue()
        ->and($target->status->value)->toBe('suspended')
        ->and($device->refresh()->status)->toBe(UserDeviceStatus::Revoked);

    $this->actingAs($admin)
        ->post(route('users.restore', ['user' => $target->uuid]))
        ->assertRedirect();

    expect($target->refresh()->trashed())->toBeFalse()
        ->and($target->status->value)->toBe('suspended');
});

it('unlocks an active user and clears pin lock counters', function (): void {
    $admin = administrationUser();
    $target = User::factory()->create([
        'status' => 'locked',
        'failed_pin_attempts' => 5,
        'locked_until' => now()->addMinutes(10),
    ]);

    $this->actingAs($admin)
        ->post(route('users.unlock', ['user' => $target->uuid]))
        ->assertRedirect();

    expect($target->refresh()->status->value)->toBe('active')
        ->and($target->failed_pin_attempts)->toBe(0)
        ->and($target->locked_until)->toBeNull();
});

it('creates custom roles and updates their permission matrix', function (): void {
    $admin = administrationUser();

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'label' => 'Supervision agence',
            'description' => 'Accès de supervision.',
            'category' => RoleCategory::Agency->value,
            'permissions' => ['agencies.view', 'users.view'],
        ])
        ->assertRedirect(route('roles', absolute: false));

    $role = Role::query()->where('label', 'Supervision agence')->firstOrFail();

    expect($role->name)->toBe('custom_supervision_agence')
        ->and($role->is_system)->toBeFalse()
        ->and($role->permissions()->pluck('name')->all())->toBe(['agencies.view', 'users.view']);

    $this->actingAs($admin)
        ->put(route('roles.permissions.update', ['role' => $role->name]), [
            'permissions' => ['agencies.view', 'users.view', 'roles.view'],
        ])
        ->assertRedirect();

    expect($role->refresh()->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['agencies.view', 'roles.view', 'users.view']);
});

it('protects system admin role metadata and matrix', function (): void {
    $admin = administrationUser();
    $role = Role::query()->where('name', 'system_admin')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('roles.update', ['role' => $role->name]), [
            'label' => 'Renamed admin',
            'description' => null,
            'category' => 'central',
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->put(route('roles.permissions.update', ['role' => $role->name]), [
            'permissions' => ['roles.view'],
        ])
        ->assertUnprocessable();
});

it('recognizes custom terrain roles in the ops guard only with departures permission', function (): void {
    $admin = administrationUser();
    $agency = Agency::query()->create([
        'code' => 'PAR',
        'name' => 'Agence Parakou',
        'city' => 'Parakou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('roles.store'), [
        'label' => 'Équipe terrain',
        'description' => null,
        'category' => 'terrain',
        'permissions' => ['departures.view'],
    ]);

    $role = Role::query()->where('label', 'Équipe terrain')->firstOrFail();
    $user = User::factory()->create(['agency_id' => $agency->id]);
    $user->assignRole($role);

    app(OpsSessionGuard::class)->assertUserAllowed($user);

    expect(true)->toBeTrue();
});
