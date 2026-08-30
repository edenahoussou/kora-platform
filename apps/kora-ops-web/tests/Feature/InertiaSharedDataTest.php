<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\SystemRole;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

it('shares authenticated users through public identifiers only', function (): void {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('auth.user')
            ->where('auth.user.uuid', $user->uuid)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.id')
        );
});

it('shares the authenticated user roles and permissions with Inertia', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(SystemRole::AgencyAgent->value);

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.user.roles', [SystemRole::AgencyAgent->value])
            ->where('auth.user.permissions', fn (Collection $permissions): bool => $permissions->sort()->values()->all()
                === collect(SystemRole::AgencyAgent->permissions())->sort()->values()->all())
        );
});
