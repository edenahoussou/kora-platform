<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(Database\Seeders\KoraBusRolePermissionSeeder::class);
});

function layoutsUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

it('serves the seat layouts index mock page', function (): void {
    $this->actingAs(layoutsUser())
        ->get(route('layouts.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page->component(
                'fleet/layouts/index',
            ),
        );
});

it('serves the layout editor in create mode with an optional duplicate source', function (): void {
    $this->actingAs(layoutsUser())
        ->get(route('layouts.create', ['duplicate' => 'tpl-standard-2plus2']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('fleet/layouts/editor')
                ->where('duplicate', 'tpl-standard-2plus2'),
        );
});

it('serves the layout editor in edit mode for a template uuid', function (): void {
    $this->actingAs(layoutsUser())
        ->get(route('layouts.edit', 'tpl-premium-2plus1'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('fleet/layouts/editor')
                ->where('templateUuid', 'tpl-premium-2plus1'),
        );
});
