<?php

use App\Models\User;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

it('filters users by search query', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    $admin = User::factory()->create(['name' => 'ZZZ Unique Marker']);
    $admin->assignRole('system_admin');
    User::factory()->create(['name' => 'Other Person']);

    $this->actingAs($admin)
        ->get('/users?q=' . urlencode('ZZZ Unique'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'ZZZ Unique Marker'));
});
