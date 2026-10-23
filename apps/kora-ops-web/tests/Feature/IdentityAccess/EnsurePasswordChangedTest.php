<?php

use App\Models\User;

it('lets a user who must change password reach the profile page instead of looping', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk();
});

it('redirects a flagged user away from other pages towards the security tab', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('security.edit'));

    $this->actingAs($user)
        ->get($response->headers->get('Location'))
        ->assertRedirect('/settings/profile?tab=security');
});

it('does not restrict a user who already changed their password', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk();
});
