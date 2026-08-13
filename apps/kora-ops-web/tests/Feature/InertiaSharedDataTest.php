<?php

use App\Models\User;
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
