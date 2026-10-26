<?php

use App\Models\User;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraServiceClassSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->seed(KoraServiceClassSeeder::class);
});

it('lists fares with line, segment, service class, and price without exposing numeric ids', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $this->actingAs(commercialDirector())
        ->get(route('fares.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fares')
            ->where('fares.data.0.uuid', $fare->uuid)
            ->where('fares.data.0.lineCode', $line->code)
            ->where('fares.data.0.lineName', $line->name)
            ->where('fares.data.0.originName', 'Cotonou')
            ->where('fares.data.0.destinationName', 'Parakou')
            ->where('fares.data.0.serviceClassCode', 'standard')
            ->where('fares.data.0.serviceClassName', 'Standard')
            ->where('fares.data.0.baseAmount', 8500)
            ->where('fares.data.0.currency', 'XOF')
            ->where('fares.data.0.status', 'active')
            ->missing('fares.data.0.id')
            ->has('lines')
            ->where('permissions.canCreate', true));
});

it('filters fares by status and service class', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();
    $premium = ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'premium', 'name' => 'KORA Premium', 'sort_order' => 2]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $premium->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'draft',
    ]);

    $this->actingAs(commercialDirector())
        ->get(route('fares.index', ['service' => 'premium']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fares.total', 1)
            ->where('fares.data.0.serviceClassCode', 'premium')
            ->where('fares.data.0.baseAmount', 10500));

    $this->actingAs(commercialDirector())
        ->get(route('fares.index', ['status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fares.total', 1)
            ->where('fares.data.0.serviceClassCode', 'standard'));
});

it('filters fares by line uuid', function (): void {
    [$lineA, $originA, $destinationA] = lineWithStops();
    [$lineB, $originB, $destinationB] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $lineA->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $originA->id,
        'destination_stop_id' => $destinationA->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $lineB->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $originB->id,
        'destination_stop_id' => $destinationB->id,
        'base_amount' => 12000,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $this->actingAs(commercialDirector())
        ->get(route('fares.index', ['line' => $lineA->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fares.total', 1)
            ->where('fares.data.0.lineCode', $lineA->code)
            ->where('fares.data.0.baseAmount', 8500));
});

it('exposes line fares in the network index payload', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();
    $premium = ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'premium', 'name' => 'KORA Premium', 'sort_order' => 2]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $premium->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $director = User::factory()->create();
    $director->assignRole('operations_director');

    $this->actingAs($director)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('network')
            ->has('lines.0.fares', 2)
            ->where('lines.0.fares.0.originName', 'Cotonou')
            ->where('lines.0.fares.0.destinationName', 'Parakou')
            ->where('lines.0.fares.0.serviceClassName', 'Standard')
            ->where('lines.0.fares.0.baseAmount', 8500)
            ->where('lines.0.fares.1.serviceClassName', 'KORA Premium')
            ->where('lines.0.fares.1.baseAmount', 10500));
});

it('excludes archived fares from the network index payload', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'archived',
    ]);

    $director = User::factory()->create();
    $director->assignRole('operations_director');

    $this->actingAs($director)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.fares', []));
});

it('rejects unauthorized fares access for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('fares.index'))->assertForbidden();
});

it('creates a fare in draft status with validation explicit required', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $this->actingAs(commercialDirector())->post(route('fares.store'), [
        'line_id' => $line->uuid,
        'service_class_id' => $standard->uuid,
        'origin_stop_id' => $origin->uuid,
        'destination_stop_id' => $destination->uuid,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
    ])->assertRedirect(route('fares.index', absolute: false));

    $fare = Fare::query()->where('line_id', $line->id)->firstOrFail();
    expect($fare->base_amount)->toBe(9000)
        ->and($fare->status->value)->toBe('draft')
        ->and($fare->effective_until)->toBeNull();
});

it('defaults to the standard service class when none is selected', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $this->actingAs(commercialDirector())->post(route('fares.store'), [
        'line_id' => $line->uuid,
        'origin_stop_id' => $origin->uuid,
        'destination_stop_id' => $destination->uuid,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
    ])->assertRedirect(route('fares.index', absolute: false));

    $fare = Fare::query()->where('line_id', $line->id)->firstOrFail();
    expect($fare->service_class_id)->toBe($standard->id);
});

it('rejects fare creation when origin equals destination', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $this->actingAs(commercialDirector())->post(route('fares.store'), [
        'line_id' => $line->uuid,
        'service_class_id' => $standard->uuid,
        'origin_stop_id' => $origin->uuid,
        'destination_stop_id' => $origin->uuid,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
    ])->assertSessionHasErrors('destination_stop_id');
});

it('rejects fare creation when effective_until is before effective_from', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $this->actingAs(commercialDirector())->post(route('fares.store'), [
        'line_id' => $line->uuid,
        'service_class_id' => $standard->uuid,
        'origin_stop_id' => $origin->uuid,
        'destination_stop_id' => $destination->uuid,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
        'effective_until' => '2026-08-01',
    ])->assertSessionHasErrors('effective_until');
});

it('activates a draft fare via status change', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
        'status' => 'draft',
    ]);

    $this->actingAs(commercialDirector())
        ->patch(route('fares.status', $fare), ['status' => 'active'])
        ->assertRedirect(route('fares.index', absolute: false));

    expect($fare->refresh()->status->value)->toBe('active');
});

it('archives an active fare via status change', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
        'status' => 'active',
    ]);

    $this->actingAs(commercialDirector())
        ->patch(route('fares.status', $fare), ['status' => 'archived'])
        ->assertRedirect(route('fares.index', absolute: false));

    expect($fare->refresh()->status->value)->toBe('archived');
});

it('updates a fare price and effective period', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8000,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'draft',
    ]);

    $this->actingAs(commercialDirector())->put(route('fares.update', $fare), [
        'base_amount' => 9500,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
        'effective_until' => '2026-12-31',
    ])->assertRedirect(route('fares.index', absolute: false));

    expect($fare->refresh()->base_amount)->toBe(9500)
        ->and($fare->refresh()->effective_until?->toDateString())->toBe('2026-12-31');
});

it('rejects fare creation from unauthorized agency agents', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->post(route('fares.store'), [
        'line_id' => $line->uuid,
        'service_class_id' => $standard->uuid,
        'origin_stop_id' => $origin->uuid,
        'destination_stop_id' => $destination->uuid,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-09-01',
    ])->assertForbidden();
});

it('shows fare detail with segment, price, reference, versions and timeline without exposing numeric ids', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $this->actingAs(commercialDirector())
        ->get(route('fares.show', $fare))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fares/detail')
            ->where('fare.uuid', $fare->uuid)
            ->where('fare.reference', "TAR-{$line->code}-standard-2026-08")
            ->where('fare.lineCode', $line->code)
            ->where('fare.lineName', $line->name)
            ->where('fare.originName', 'Cotonou')
            ->where('fare.destinationName', 'Parakou')
            ->where('fare.serviceClassCode', 'standard')
            ->where('fare.serviceClassName', 'Standard')
            ->where('fare.baseAmount', 8500)
            ->where('fare.currency', 'XOF')
            ->where('fare.status', 'active')
            ->missing('fare.id')
            ->has('versions', 1)
            ->where('versions.0.uuid', $fare->uuid)
            ->where('versions.0.isCurrent', true)
            ->has('timeline')
            ->missing('serviceClasses')
            ->has('lines')
            ->where('permissions.canUpdate', true));
});

it('lists sibling fares on the same segment as versions ordered by effective_from', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $older = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 7000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-07-31',
        'status' => 'archived',
    ]);

    $current = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $this->actingAs(commercialDirector())
        ->get(route('fares.show', $current))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('versions', 2)
            ->where('versions.0.uuid', $older->uuid)
            ->where('versions.0.effectiveFrom', '2026-01-01')
            ->where('versions.0.isCurrent', false)
            ->where('versions.1.uuid', $current->uuid)
            ->where('versions.1.effectiveFrom', '2026-08-01')
            ->where('versions.1.isCurrent', true));
});

it('rejects unauthorized fare detail access for agency agents', function (): void {
    [$line, $origin, $destination] = lineWithStops();
    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $standard->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 8500,
        'currency' => 'XOF',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('fares.show', $fare))->assertForbidden();
});
