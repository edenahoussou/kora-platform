<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function agencyManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('commercial_director');

    return $user;
}

it('lists agencies for authorized management users without exposing numeric ids', function (): void {
    $user = agencyManager();
    $agency = Agency::query()->create([
        'code' => 'COT',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('agencies.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agencies')
            ->where('agencies.data.0.uuid', $agency->uuid)
            ->missing('agencies.data.0.id')
            ->where('agencies.current_page', 1)
            ->where('agencies.total', 1)
            ->where('permissions.canCreate', true)
            ->where('permissions.canUpdate', true)
            ->where('permissions.canDeactivate', true),
        );
});

it('creates and updates an agency through the web module', function (): void {
    $user = agencyManager();

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            'name' => 'Agence Cotonou Centre',
            'city' => 'Cotonou',
            'country' => 'bj',
            'status' => 'draft',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    $agency = Agency::query()->where('name', 'Agence Cotonou Centre')->firstOrFail();

    expect($agency->uuid)->not->toBeEmpty()
        ->and($agency->code)->toMatch('/^AG-\d{6}$/')
        ->and($agency->country)->toBe('BJ')
        ->and($agency->status->value)->toBe('draft');

    $this->actingAs($user)
        ->put(route('agencies.update', $agency), [
            'name' => 'Agence Cotonou Centre rénovée',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    expect($agency->refresh()->name)->toBe('Agence Cotonou Centre rénovée')
        ->and($agency->status->value)->toBe('active');
});

it('auto-generates sequential, unique agency codes and ignores any client-supplied code', function (): void {
    $user = agencyManager();

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            // A client-supplied `code` must be silently ignored: it is not
            // even part of the validated payload.
            'code' => 'HACKED',
            'name' => 'Agence Un',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'draft',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            'name' => 'Agence Deux',
            'city' => 'Parakou',
            'country' => 'BJ',
            'status' => 'draft',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    $first = Agency::query()->where('name', 'Agence Un')->firstOrFail();
    $second = Agency::query()->where('name', 'Agence Deux')->firstOrFail();

    expect($first->code)->not->toBe('HACKED')
        ->and($first->code)->toMatch('/^AG-\d{6}$/')
        ->and($second->code)->toMatch('/^AG-\d{6}$/')
        ->and($first->code)->not->toBe($second->code);

    // Sequence keeps advancing, so codes are monotonically increasing.
    expect((int) substr($second->code, 3))->toBeGreaterThan((int) substr($first->code, 3));
});

it('keeps the agency code unchanged when a client attempts to modify it on update', function (): void {
    $user = agencyManager();
    $agency = Agency::query()->create([
        'code' => 'AG-000010',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->put(route('agencies.update', $agency), [
            'code' => 'AG-999999',
            'name' => 'Agence Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    expect($agency->refresh()->code)->toBe('AG-000010');
});

it('forbids users without the agency permission from accessing agencies', function (): void {
    $agency = Agency::query()->create([
        'code' => 'COT',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)
        ->get(route('agencies.index'))
        ->assertForbidden();

    expect($agency->exists)->toBeTrue();
});

it('supports suspension, archiving, and reactivation without deleting the agency', function (): void {
    $user = agencyManager();
    $agency = Agency::query()->create([
        'code' => 'COT',
        'name' => 'Agence Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    foreach (['suspended', 'archived', 'active'] as $status) {
        $this->actingAs($user)
            ->patch(route('agencies.status', $agency), ['status' => $status])
            ->assertRedirect(route('agencies.index', absolute: false));

        expect($agency->refresh()->status->value)->toBe($status);
    }

    expect(Agency::withTrashed()->find($agency->id))->not->toBeNull();
});

it('assigns and updates the agency head', function (): void {
    $user = agencyManager();

    $manager = User::factory()->create();
    $manager->assignRole('agency_manager');

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            'name' => 'Agence Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'head_user_id' => $manager->id,
            'status' => 'active',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    $agency = Agency::query()->where('name', 'Agence Cotonou')->firstOrFail();

    expect($agency->head_user_id)->toBe($manager->id)
        ->and($agency->head->name)->toBe($manager->name);

    $this->actingAs($user)
        ->get(route('agencies.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.data.0.headName', $manager->name)
            ->where('agencies.data.0.headUserId', $manager->id),
        );

    $other = User::factory()->create();
    $other->assignRole('agency_manager');

    $this->actingAs($user)
        ->put(route('agencies.update', $agency), [
            'name' => 'Agence Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'head_user_id' => $other->id,
            'status' => 'active',
        ])
        ->assertRedirect(route('agencies.index', absolute: false));

    expect($agency->refresh()->head_user_id)->toBe($other->id);
});

it('filters agencies by search query and status via URL params', function (): void {
    $user = agencyManager();

    Agency::query()->create([
        'code' => 'COT', 'name' => 'Agence Cotonou', 'city' => 'Cotonou',
        'country' => 'BJ', 'status' => 'active',
    ]);
    Agency::query()->create([
        'code' => 'PAR', 'name' => 'Agence Parakou', 'city' => 'Parakou',
        'country' => 'BJ', 'status' => 'draft',
    ]);
    Agency::query()->create([
        'code' => 'KRI', 'name' => 'Agence Kribi', 'city' => 'Kribi',
        'country' => 'BJ', 'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('agencies.index', ['q' => 'parakou']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.total', 1)
            ->where('agencies.data.0.code', 'PAR'),
        );

    $this->actingAs($user)
        ->get(route('agencies.index', ['status' => 'draft']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.total', 1)
            ->where('agencies.data.0.code', 'PAR'),
        );

    $this->actingAs($user)
        ->get(route('agencies.index', ['q' => 'cotonou', 'status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.total', 1)
            ->where('agencies.data.0.code', 'COT'),
        );
});

it('paginates agencies with 15 items per page', function (): void {
    $user = agencyManager();

    for ($i = 0; $i < 18; $i++) {
        Agency::query()->create([
            'code' => sprintf('AG%02d', $i),
            'name' => "Agence {$i}",
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ]);
    }

    $this->actingAs($user)
        ->get(route('agencies.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.total', 18)
            ->where('agencies.per_page', 15)
            ->where('agencies.last_page', 2)
            ->where('agencies.current_page', 1)
            ->has('agencies.data', 15),
        );

    $this->actingAs($user)
        ->get(route('agencies.index', ['page' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.current_page', 2)
            ->has('agencies.data', 3),
        );
});
