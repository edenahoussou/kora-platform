<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Models\Promotion;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function promotionManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('commercial_director');

    return $user;
}

/** @return array<string, mixed> */
function promotionPayload(array $overrides = []): array
{
    return [
        'name' => 'Promo Nord',
        'code' => 'nord10',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->addMonth()->toDateTimeString(),
        ...$overrides,
    ];
}

function promotionLine(): array
{
    $line = Line::query()->create([
        'code' => 'PM-L1',
        'name' => 'Cotonou-Parakou',
        'countries_crossed' => ['BJ'],
        'status' => 'active',
    ]);
    $originPhysical = PhysicalStop::query()->create(['name' => 'Cotonou Gare', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::query()->create(['name' => 'Parakou Gare', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active']);
    $origin = Stop::query()->create(['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::query()->create(['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

    return [$line, $origin, $destination];
}

it('lists promotions with period, scope, rule, priority and status', function (): void {
    $user = promotionManager();
    $promotion = Promotion::query()->create([
        'name' => 'Promo Ramadan',
        'code' => 'RAMADAN10',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 20,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('promotions.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('promotions')
            ->where('promotions.data.0.uuid', $promotion->uuid)
            ->where('promotions.data.0.code', 'RAMADAN10')
            ->where('promotions.data.0.priority', 20)
            ->where('promotions.data.0.displayStatus', 'active')
            ->missing('promotions.data.0.id')
            ->has('promotions.data.0.startsAt')
            ->has('promotions.data.0.scopeType')
            ->has('promotions.data.0.benefitType')
            ->where('permissions.canUpdate', true),
        );
});

it('creates a promotion in draft with the code normalized to uppercase', function (): void {
    $user = promotionManager();

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload())
        ->assertRedirect(route('promotions.index', absolute: false));

    $promotion = Promotion::query()->where('code', 'NORD10')->firstOrFail();
    expect($promotion->status->value)->toBe('draft')
        ->and($promotion->created_by)->toBe($user->id);
});

it('rejects management actions without the pricing.manage permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('daf');

    $this->actingAs($user)
        ->get(route('promotions.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload())
        ->assertForbidden();
});

it('validates the code format and the benefit value bounds', function (): void {
    $user = promotionManager();

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload(['code' => 'PROMO!']))
        ->assertSessionHasErrors('code');

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload(['code' => 'PCT200', 'benefit_value' => 200]))
        ->assertSessionHasErrors('benefit_value');

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload([
            'code' => 'FIXE0',
            'benefit_type' => 'fixed_amount',
            'benefit_value' => 0,
        ]))
        ->assertSessionHasErrors('benefit_value');
});

it('requires the scope attributes matching the chosen perimeter', function (): void {
    $user = promotionManager();
    [$line, $origin, $destination] = promotionLine();

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload([
            'code' => 'CANAL',
            'scope_type' => 'channel',
        ]))
        ->assertSessionHasErrors('channel');

    $otherLine = Line::query()->create(['code' => 'PM-L2', 'name' => 'Autre ligne', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $foreignPhysical = PhysicalStop::query()->create(['name' => 'Natitingou', 'city' => 'Natitingou', 'country' => 'BJ', 'status' => 'active']);
    $foreignStop = Stop::query()->create(['line_id' => $otherLine->id, 'physical_stop_id' => $foreignPhysical->id, 'name' => 'Natitingou', 'city' => 'Natitingou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload([
            'code' => 'SEGMENT',
            'scope_type' => 'segment',
            'line_id' => $line->uuid,
            'origin_stop_id' => $origin->uuid,
            'destination_stop_id' => $foreignStop->uuid,
        ]))
        ->assertSessionHasErrors('destination_stop_id');
});

it('reports scope, priority and period conflicts before saving and accepts an explicit force', function (): void {
    $user = promotionManager();
    Promotion::query()->create([
        'name' => 'Promo existante',
        'code' => 'EXIST',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload(['code' => 'CLASH']));

    $response->assertSessionHasErrors('conflicts');
    expect(Promotion::query()->where('code', 'CLASH')->exists())->toBeFalse();

    $conflicts = json_decode((string) session('errors')->getBag('default')->first('conflicts'), true);
    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['code'])->toBe('EXIST');

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload(['code' => 'CLASH', 'force' => true]))
        ->assertRedirect(route('promotions.index', absolute: false));

    expect(Promotion::query()->where('code', 'CLASH')->exists())->toBeTrue();
});

it('ignores conflicts with a different priority or a disjoint period', function (): void {
    $user = promotionManager();
    Promotion::query()->create([
        'name' => 'Promo existante',
        'code' => 'EXIST',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload(['code' => 'OTHERPRIO', 'priority' => 99]))
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($user)
        ->post(route('promotions.store'), promotionPayload([
            'code' => 'DISJOINT',
            'starts_at' => now()->addMonths(2)->toDateTimeString(),
            'ends_at' => now()->addMonths(3)->toDateTimeString(),
        ]))
        ->assertSessionDoesntHaveErrors();
});

it('updates a promotion and refuses to edit an archived one', function (): void {
    $user = promotionManager();
    $promotion = Promotion::query()->create([
        'name' => 'Promo à modifier',
        'code' => 'EDITME',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->put(route('promotions.update', $promotion->uuid), promotionPayload([
            'name' => 'Promo modifiée',
            'code' => 'EDITME',
            'benefit_value' => 25,
        ]))
        ->assertRedirect(route('promotions.index', absolute: false));

    expect($promotion->refresh()->benefit_value)->toEqual('25.0000');

    $promotion->update(['status' => 'archived']);
    $this->actingAs($user)
        ->put(route('promotions.update', $promotion->uuid), promotionPayload(['code' => 'EDITME']))
        ->assertSessionHasErrors('promotion');
});

it('walks the lifecycle draft → active ⇄ inactive → archived and rejects invalid transitions', function (): void {
    $user = promotionManager();
    $promotion = Promotion::query()->create([
        'name' => 'Promo cycle',
        'code' => 'CYCLE',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->patch(route('promotions.status', $promotion->uuid), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');

    $this->actingAs($user)
        ->patch(route('promotions.status', $promotion->uuid), ['status' => 'active'])
        ->assertRedirect();
    expect($promotion->refresh()->status->value)->toBe('active');

    $this->actingAs($user)
        ->patch(route('promotions.status', $promotion->uuid), ['status' => 'inactive'])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('promotions.status', $promotion->uuid), ['status' => 'archived'])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('promotions.status', $promotion->uuid), ['status' => 'active'])
        ->assertSessionHasErrors('status');
});
