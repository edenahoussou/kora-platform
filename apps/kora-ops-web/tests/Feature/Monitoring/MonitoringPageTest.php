<?php

use App\Models\User;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('forbids the monitoring screen to users without the monitoring permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');

    $this->actingAs($user)
        ->get(route('administration.monitoring'))
        ->assertForbidden();
});

it('forbids the log viewer api to users without the monitoring permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');

    $this->actingAs($user)
        ->getJson('/log-viewer/api/files')
        ->assertForbidden();
});

it('renders the monitoring screen with health checks for system admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get(route('administration.monitoring'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/monitoring')
            ->has('app')
            ->has('checks')
            ->where('app.environment', 'testing')
            ->where('logViewer.apiBase', url('/log-viewer/api')),
        );
});

it('exposes the log viewer api to users with the monitoring permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->getJson('/log-viewer/api/files')
        ->assertSuccessful();
});
