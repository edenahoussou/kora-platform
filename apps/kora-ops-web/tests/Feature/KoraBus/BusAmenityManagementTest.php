<?php

use App\Models\User;
use App\Modules\BusNetwork\Data\SeatType;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusAmenity;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function operationsDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole('operations_director');

    return $user;
}

function createAmenity(array $overrides = []): BusAmenity
{
    return BusAmenity::query()->create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'code' => 'wifi',
        'label' => 'Wi-Fi à bord',
        'icon' => 'wifi',
        'status' => 'active',
    ], $overrides));
}

it('lists amenities without exposing numeric ids', function (): void {
    $amenity = createAmenity();

    $this->actingAs(operationsDirector())
        ->get(route('amenities.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('amenities')
            ->where('amenities.data.0.uuid', $amenity->uuid)
            ->where('amenities.data.0.code', 'wifi')
            ->where('amenities.data.0.label', 'Wi-Fi à bord')
            ->where('amenities.data.0.icon', 'wifi')
            ->where('amenities.data.0.busesCount', 0)
            ->where('amenities.data.0.status', 'active')
            ->missing('amenities.data.0.id')
            ->where('permissions.canCreate', true));
});

it('filters amenities by search and status', function (): void {
    createAmenity(['code' => 'wifi', 'label' => 'Wi-Fi à bord']);
    createAmenity(['code' => 'air_conditioning', 'label' => 'Climatisation', 'status' => 'archived']);

    $this->actingAs(operationsDirector())
        ->get(route('amenities.index', ['q' => 'clim']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('amenities.total', 1)
            ->where('amenities.data.0.code', 'air_conditioning'));

    $this->actingAs(operationsDirector())
        ->get(route('amenities.index', ['status' => 'archived']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('amenities.total', 1)
            ->where('amenities.data.0.code', 'air_conditioning'));
});

it('creates an amenity', function (): void {
    $this->actingAs(operationsDirector())->post(route('amenities.store'), [
        'code' => 'usb_charging',
        'label' => 'Prises USB',
        'icon' => 'usb',
        'status' => 'active',
    ])->assertRedirect(route('amenities.index', absolute: false));

    $amenity = BusAmenity::query()->where('code', 'usb_charging')->firstOrFail();
    expect($amenity->label)->toBe('Prises USB')
        ->and($amenity->status->value)->toBe('active');
});

it('rejects duplicate amenity codes', function (): void {
    createAmenity(['code' => 'wifi']);

    $this->actingAs(operationsDirector())->post(route('amenities.store'), [
        'code' => 'wifi',
        'label' => 'Wi-Fi (doublon)',
        'status' => 'active',
    ])->assertSessionHasErrors('code');
});

it('updates an amenity', function (): void {
    $amenity = createAmenity(['label' => 'Wi-Fi à bord']);

    $this->actingAs(operationsDirector())->put(route('amenities.update', $amenity), [
        'code' => $amenity->code,
        'label' => 'Wi-Fi haut débit',
        'icon' => 'wifi',
        'status' => 'active',
    ])->assertRedirect(route('amenities.index', absolute: false));

    expect($amenity->refresh()->label)->toBe('Wi-Fi haut débit');
});

it('archives an amenity via status change', function (): void {
    $amenity = createAmenity();

    $this->actingAs(operationsDirector())
        ->patch(route('amenities.status', $amenity), ['status' => 'archived'])
        ->assertRedirect(route('amenities.index', absolute: false));

    expect($amenity->refresh()->status->value)->toBe('archived');
});

it('rejects unauthorized amenity management for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('amenities.index'))->assertForbidden();

    $amenity = createAmenity();
    $this->actingAs($agent)->post(route('amenities.store'), [
        'code' => 'usb_charging',
        'label' => 'Prises USB',
        'status' => 'active',
    ])->assertForbidden();
    $this->actingAs($agent)->put(route('amenities.update', $amenity), [
        'code' => $amenity->code,
        'label' => 'Modifié',
        'status' => 'active',
    ])->assertForbidden();
    $this->actingAs($agent)
        ->patch(route('amenities.status', $amenity), ['status' => 'archived'])
        ->assertForbidden();
});

it('attaches amenities to a bus on creation and reflects them on the fleet detail page', function (): void {
    $wifi = createAmenity(['code' => 'wifi', 'label' => 'Wi-Fi à bord']);
    $ac = createAmenity(['code' => 'air_conditioning', 'label' => 'Climatisation']);

    $composition = BusComposition::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'COMP-'.Str::random(6),
        'name' => 'Coach 55',
        'seat_count' => 4,
        'layout' => [['type' => SeatType::Standard->value, 'codes' => ['1A', '1B', '1C', '1D']]],
        'status' => 'active',
    ]);

    $model = BusModel::query()->create([
        'uuid' => Str::uuid()->toString(),
        'brand' => 'KORA',
        'name' => 'Coach 55',
        'default_seat_count' => 4,
        'status' => 'active',
    ]);

    $this->actingAs(operationsDirector())->post(route('fleet.store'), [
        'registration_number' => 'BJ-1234-AB',
        'bus_model_id' => $model->id,
        'composition_id' => $composition->id,
        'status' => 'active',
        'amenity_ids' => [$wifi->id, $ac->id],
    ])->assertRedirect(route('fleet.index', absolute: false));

    $bus = Bus::query()->where('registration_number', 'BJ-1234-AB')->firstOrFail();
    expect($bus->amenities()->pluck('bus_amenities.code')->sort()->values()->all())
        ->toBe(['air_conditioning', 'wifi']);

    $this->actingAs(operationsDirector())
        ->get(route('fleet.show', $bus))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fleet/detail')
            ->has('bus.amenities', 2)
            ->where('bus.amenityIds', fn ($ids) => $ids->contains($wifi->id) && $ids->contains($ac->id)));
});
