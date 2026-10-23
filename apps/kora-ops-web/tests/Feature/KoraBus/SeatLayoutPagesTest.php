<?php

use App\Models\User;
use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\SeatStatusEvent;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function layoutsUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

/** @return array{rows: int, cols: int, cells: list<array<string, mixed>>} */
function validGrid(): array
{
    return [
        'rows' => 2,
        'cols' => 4,
        'cells' => [
            ['kind' => 'driver', 'x' => 0, 'y' => 0],
            ['kind' => 'door', 'x' => 3, 'y' => 0],
            ['kind' => 'seat', 'x' => 0, 'y' => 1, 'code' => '1A', 'seatKind' => 'standard'],
            ['kind' => 'seat', 'x' => 1, 'y' => 1, 'code' => '1B', 'seatKind' => 'standard'],
            ['kind' => 'aisle', 'x' => 2, 'y' => 1],
            ['kind' => 'seat', 'x' => 3, 'y' => 1, 'code' => '1C', 'seatKind' => 'crew'],
        ],
    ];
}

/** @param array<string, mixed> $overrides */
function makeComposition(array $overrides = []): BusComposition
{
    return BusComposition::query()->create(array_merge([
        'code' => 'GAB-900',
        'name' => 'Standard test',
        'seat_count' => 2,
        'layout' => [],
        'grid' => validGrid(),
        'status' => CompositionStatus::Draft->value,
    ], $overrides));
}

it('serves the layouts index with serialized templates', function (): void {
    makeComposition();

    $this->actingAs(layoutsUser())
        ->get(route('layouts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('fleet/layouts/index')
            ->has('templates', 1)
            ->has('templates.0.grid.cells')
            ->where('templates.0.code', 'GAB-900')
            ->has('stats')
            ->has('permissions'));
});

it('serves the create editor with the next generated code preview', function (): void {
    makeComposition(['code' => 'GAB-007']);

    $this->actingAs(layoutsUser())
        ->get(route('layouts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('fleet/layouts/editor')
            ->where('nextCode', 'GAB-008'));
});

it('forbids the index to users without fleet permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('layouts.index'))->assertForbidden();
});

it('creates a layout with an automatically generated code', function (): void {
    $this->actingAs(layoutsUser())
        ->post(route('layouts.store'), [
            'name' => 'Premium 2+1',
            'description' => 'Version premium',
            'grid' => validGrid(),
        ])
        ->assertRedirect(route('layouts.index'));

    $composition = BusComposition::query()->where('name', 'Premium 2+1')->firstOrFail();
    expect($composition->code)->toMatch('/^GAB-\d{3}$/')
        ->and($composition->status)->toBe(CompositionStatus::Draft)
        ->and($composition->seat_count)->toBe(3);
});

it('generates sequential codes and ignores a client-provided code', function (): void {
    makeComposition(['code' => 'GAB-007']);

    $this->actingAs(layoutsUser())
        ->post(route('layouts.store'), [
            'name' => 'Auto',
            'code' => 'HACK-999',
            'grid' => validGrid(),
        ])
        ->assertRedirect(route('layouts.index'));

    expect(BusComposition::query()->where('name', 'Auto')->value('code'))->toBe('GAB-008');
});

it('rejects an invalid grid with French validation errors', function (): void {
    $grid = validGrid();
    $grid['cells'][] = ['kind' => 'driver', 'x' => 1, 'y' => 0];

    $this->actingAs(layoutsUser())
        ->from(route('layouts.create'))
        ->post(route('layouts.store'), ['name' => 'Bad', 'grid' => $grid])
        ->assertSessionHasErrors('grid');

    expect(BusComposition::query()->where('name', 'Bad')->exists())->toBeFalse();
});

it('requires a name and at least one sellable seat', function (): void {
    $grid = ['rows' => 1, 'cols' => 2, 'cells' => [
        ['kind' => 'seat', 'x' => 0, 'y' => 0, 'code' => 'EQ1', 'seatKind' => 'crew'],
    ]];

    $this->actingAs(layoutsUser())
        ->post(route('layouts.store'), ['name' => '', 'grid' => $grid])
        ->assertSessionHasErrors(['name', 'grid']);
});

it('rejects duplicated seat codes', function (): void {
    $grid = validGrid();
    $grid['cells'][] = ['kind' => 'seat', 'x' => 1, 'y' => 0, 'code' => '1A'];

    $this->actingAs(layoutsUser())
        ->post(route('layouts.store'), ['name' => 'Dup', 'grid' => $grid])
        ->assertSessionHasErrors('grid');
});

it('updates a draft layout', function (): void {
    $composition = makeComposition();
    $grid = validGrid();
    $grid['cells'][] = ['kind' => 'seat', 'x' => 2, 'y' => 0, 'code' => '2A'];

    $this->actingAs(layoutsUser())
        ->put(route('layouts.update', $composition), [
            'name' => 'Renommé',
            'description' => 'Nouvelle description',
            'grid' => $grid,
        ])
        ->assertRedirect(route('layouts.index'));

    expect($composition->refresh()->name)->toBe('Renommé')
        ->and($composition->seat_count)->toBe(4);
});

it('blocks updates on a layout used by a bus', function (): void {
    $composition = makeComposition(['status' => CompositionStatus::Active->value]);
    Bus::query()->create([
        'registration_number' => 'LOCK-1',
        'composition_id' => $composition->id,
        'seat_count' => 2,
        'status' => 'active',
    ]);

    $this->actingAs(layoutsUser())
        ->put(route('layouts.update', $composition), [
            'name' => 'Tentative',
            'grid' => validGrid(),
        ])
        ->assertSessionHasErrors('grid');

    expect($composition->refresh()->name)->toBe('Standard test');
});

it('duplicates a layout into a draft with a fresh code', function (): void {
    $composition = makeComposition(['status' => CompositionStatus::Active->value]);

    $this->actingAs(layoutsUser())
        ->post(route('layouts.duplicate', $composition))
        ->assertRedirect();

    $copy = BusComposition::query()->where('name', 'Copie de Standard test')->firstOrFail();
    expect($copy->code)->not->toBe($composition->code)
        ->and($copy->code)->toMatch('/^GAB-\d{3}$/')
        ->and($copy->status)->toBe(CompositionStatus::Draft)
        ->and($copy->grid)->toBe($composition->grid);
});

it('archives and restores an unused layout', function (): void {
    $composition = makeComposition(['status' => CompositionStatus::Active->value]);

    $this->actingAs(layoutsUser())
        ->patch(route('layouts.status', $composition), ['status' => 'archived'])
        ->assertRedirect();

    expect($composition->refresh()->status)->toBe(CompositionStatus::Archived);

    $this->actingAs(layoutsUser())
        ->patch(route('layouts.status', $composition), ['status' => 'draft'])
        ->assertRedirect();

    expect($composition->refresh()->status)->toBe(CompositionStatus::Draft);
});

it('refuses to archive a layout used by a bus', function (): void {
    $composition = makeComposition(['status' => CompositionStatus::Active->value]);
    Bus::query()->create([
        'registration_number' => 'LOCK-2',
        'composition_id' => $composition->id,
        'seat_count' => 2,
        'status' => 'active',
    ]);

    $this->actingAs(layoutsUser())
        ->patch(route('layouts.status', $composition), ['status' => 'archived'])
        ->assertSessionHasErrors('status');

    expect($composition->refresh()->status)->toBe(CompositionStatus::Active);
});

it('serves the editor with the serialized template', function (): void {
    $composition = makeComposition();

    $this->actingAs(layoutsUser())
        ->get(route('layouts.edit', $composition))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('fleet/layouts/editor')
            ->where('template.uuid', $composition->uuid)
            ->has('template.grid.cells'));
});

it('updates a seat operational status with a persisted event', function (): void {
    $bus = Bus::query()->create([
        'registration_number' => 'SEAT-1',
        'seat_count' => 1,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $this->actingAs(layoutsUser())
        ->patch(route('fleet.seats.status', [$bus, $seat]), [
            'status' => 'maintenance',
            'reason' => 'Accoudoir cassé',
        ])
        ->assertRedirect();

    expect($seat->refresh()->status->value)->toBe('maintenance')
        ->and($seat->status_reason)->toBe('Accoudoir cassé');

    $event = SeatStatusEvent::query()->where('seat_id', $seat->id)->firstOrFail();
    expect($event->to_status->value)->toBe('maintenance')
        ->and($event->reason)->toBe('Accoudoir cassé');
});

it('requires a reason when a seat is not set back to active', function (): void {
    $bus = Bus::query()->create([
        'registration_number' => 'SEAT-2',
        'seat_count' => 1,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $this->actingAs(layoutsUser())
        ->patch(route('fleet.seats.status', [$bus, $seat]), ['status' => 'blocked'])
        ->assertSessionHasErrors('reason');

    expect($seat->refresh()->status->value)->toBe('active');
});

it('returns 404 when the seat belongs to another bus', function (): void {
    $bus = Bus::query()->create(['registration_number' => 'SEAT-3', 'seat_count' => 1, 'status' => 'active']);
    $other = Bus::query()->create(['registration_number' => 'SEAT-4', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::query()->create([
        'bus_id' => $other->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $this->actingAs(layoutsUser())
        ->patch(route('fleet.seats.status', [$bus, $seat]), [
            'status' => 'blocked',
            'reason' => 'Test',
        ])
        ->assertNotFound();
});
