<?php

use App\Models\User;
use Database\Seeders\KoraBusRolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('forbids the horizon dashboard to users without the monitoring permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});

it('forbids the horizon failed jobs api to users without the monitoring permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');

    $this->actingAs($user)
        ->getJson('/horizon/api/jobs/failed')
        ->assertForbidden();
});

it('exposes the horizon dashboard to users with the monitoring permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get('/horizon')
        ->assertSuccessful();
});
