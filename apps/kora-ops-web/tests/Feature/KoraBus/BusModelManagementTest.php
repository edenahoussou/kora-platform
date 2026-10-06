<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\BusModel;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function modelOperationsDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

function createBusModel(array $overrides = []): BusModel
{
    return BusModel::query()->create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'brand' => 'Yutong',
        'name' => 'ZK6129',
        'default_seat_count' => 55,
        'status' => 'active',
    ], $overrides));
}

it('lists bus models without exposing numeric ids', function (): void {
    $model = createBusModel();

    $this->actingAs(modelOperationsDirector())
        ->get(route('models.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('models')
            ->where('models.data.0.uuid', $model->uuid)
            ->where('models.data.0.brand', 'Yutong')
            ->where('models.data.0.name', 'ZK6129')
            ->where('models.data.0.defaultSeatCount', 55)
            ->where('models.data.0.busesCount', 0)
            ->where('models.data.0.status', 'active')
            ->missing('models.data.0.id')
            ->where('permissions.canCreate', true));
});

it('filters bus models by search and status', function (): void {
    createBusModel(['brand' => 'Yutong', 'name' => 'ZK6129']);
    createBusModel(['brand' => 'Toyota', 'name' => 'Hiace', 'status' => 'archived']);

    $this->actingAs(modelOperationsDirector())
        ->get(route('models.index', ['q' => 'hiace']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('models.total', 1)
            ->where('models.data.0.name', 'Hiace'));

    $this->actingAs(modelOperationsDirector())
        ->get(route('models.index', ['status' => 'archived']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('models.total', 1)
            ->where('models.data.0.name', 'Hiace'));
});

it('creates a bus model', function (): void {
    $this->actingAs(modelOperationsDirector())->post(route('models.store'), [
        'brand' => 'Mercedes-Benz',
        'name' => 'Tourismo',
        'default_seat_count' => 50,
        'status' => 'active',
    ])->assertRedirect(route('models.index', absolute: false));

    $model = BusModel::query()->where('brand', 'Mercedes-Benz')->firstOrFail();
    expect($model->name)->toBe('Tourismo')
        ->and($model->default_seat_count)->toBe(50)
        ->and($model->status->value)->toBe('active');
});

it('rejects duplicate brand and name pairs', function (): void {
    createBusModel(['brand' => 'Yutong', 'name' => 'ZK6129']);

    $this->actingAs(modelOperationsDirector())->post(route('models.store'), [
        'brand' => 'Yutong',
        'name' => 'ZK6129',
        'default_seat_count' => 55,
        'status' => 'active',
    ])->assertSessionHasErrors('name');
});

it('updates a bus model', function (): void {
    $model = createBusModel();

    $this->actingAs(modelOperationsDirector())->put(route('models.update', $model), [
        'brand' => 'Yutong',
        'name' => 'ZK6129 Luxury',
        'default_seat_count' => 48,
        'status' => 'active',
    ])->assertRedirect(route('models.index', absolute: false));

    $model->refresh();
    expect($model->name)->toBe('ZK6129 Luxury')
        ->and($model->default_seat_count)->toBe(48);
});

it('archives a bus model via status change', function (): void {
    $model = createBusModel();

    $this->actingAs(modelOperationsDirector())
        ->patch(route('models.status', $model), ['status' => 'archived'])
        ->assertRedirect(route('models.index', absolute: false));

    expect($model->refresh()->status->value)->toBe('archived');
});

it('rejects unauthorized bus model management for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('models.index'))->assertForbidden();

    $model = createBusModel();
    $this->actingAs($agent)->post(route('models.store'), [
        'brand' => 'Mercedes-Benz',
        'name' => 'Tourismo',
        'default_seat_count' => 50,
        'status' => 'active',
    ])->assertForbidden();
    $this->actingAs($agent)->put(route('models.update', $model), [
        'brand' => 'Yutong',
        'name' => 'Modifié',
        'default_seat_count' => 50,
        'status' => 'active',
    ])->assertForbidden();
    $this->actingAs($agent)
        ->patch(route('models.status', $model), ['status' => 'archived'])
        ->assertForbidden();
});
