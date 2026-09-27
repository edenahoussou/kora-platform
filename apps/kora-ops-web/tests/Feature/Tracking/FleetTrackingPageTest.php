<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('requires authentication to access fleet tracking page', function (): void {
    $this->get(route('operations.fleet-tracking'))->assertRedirect(route('login'));
});

it('denies access to an authenticated user without tracking permissions', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('operations.fleet-tracking'))
        ->assertForbidden();
});

it('renders fleet tracking page for operations director with network scope', function (): void {
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    $this->actingAs($user)
        ->get(route('operations.fleet-tracking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operations/fleet-tracking')
            ->has('sessions')
            ->has('lines')
            ->has('agencies')
            ->where('canViewNetwork', true)
            ->has('filters')
        );
});

it('renders fleet tracking page for agency manager scoped to own agency without global agencies list', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);
    $user->assignRole('agency_manager');

    $this->actingAs($user)
        ->get(route('operations.fleet-tracking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operations/fleet-tracking')
            ->has('sessions')
            ->has('lines')
            ->where('agencies', [])
            ->where('canViewNetwork', false)
            ->where('userAgencyUuid', $agency->uuid)
        );
});
