<?php

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\ServiceClassStatus;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Database\Seeders\KoraServiceClassSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('seeds the single Standard service class', function (): void {
    $this->seed(KoraServiceClassSeeder::class);

    $standard = ServiceClass::query()->where('code', 'standard')->firstOrFail();

    expect($standard->name)->toBe('Standard')
        ->and($standard->sort_order)->toBe(0)
        ->and($standard->status)->toBe(ServiceClassStatus::Active);
});

it('is idempotent and does not duplicate service classes on reseed', function (): void {
    $this->seed(KoraServiceClassSeeder::class);
    $this->seed(KoraServiceClassSeeder::class);

    expect(ServiceClass::query()->count())->toBe(1);
});

it('hides numeric ids and uses uuid as route key', function (): void {
    $class = ServiceClass::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'business',
        'name' => 'KORA Business',
    ]);

    expect($class->id)->toBeInt()
        ->and($class->uuid)->not()->toBeEmpty()
        ->and($class->getRouteKeyName())->toBe('uuid')
        ->and($class->toArray())->not()->toHaveKey('id');
});

it('casts status to the ServiceClassStatus enum', function (): void {
    $class = ServiceClass::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'vip',
        'name' => 'KORA VIP',
        'status' => 'archived',
    ]);

    expect($class->status)->toBe(ServiceClassStatus::Archived)
        ->and($class->status->value)->toBe('archived');
});

it('orders service classes by sort order then name', function (): void {
    ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'vip', 'name' => 'KORA VIP', 'sort_order' => 3]);
    ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'confort', 'name' => 'KORA Confort', 'sort_order' => 1]);
    ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'premium', 'name' => 'KORA Premium', 'sort_order' => 2]);

    $ordered = ServiceClass::ordered()->pluck('code')->all();

    expect($ordered)->toBe(['standard', 'confort', 'premium', 'vip']);
});

it('enforces unique service class codes', function (): void {
    ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'confort', 'name' => 'KORA Confort']);

    expect(fn () => ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'confort', 'name' => 'Duplicate']))
        ->toThrow(QueryException::class);
});

it('links a fare to a service class through the belongsTo relationship', function (): void {
    $serviceClass = ServiceClass::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'confort', 'name' => 'KORA Confort']);

    $line = Line::query()->create(['uuid' => Str::uuid()->toString(), 'code' => 'L'.Str::random(6), 'name' => 'Cotonou - Parakou', 'status' => 'active']);
    $originPhysical = PhysicalStop::query()->create(['uuid' => Str::uuid()->toString(), 'name' => 'Cotonou SC Physical', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $origin = Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);
    $destinationPhysical = PhysicalStop::query()->create(['uuid' => Str::uuid()->toString(), 'name' => 'Parakou SC Physical', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active']);
    $destination = Stop::query()->create(['uuid' => Str::uuid()->toString(), 'line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 420, 'status' => 'active']);

    $fare = Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'service_class_id' => $serviceClass->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 12000,
        'currency' => 'XOF',
        'effective_from' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect($fare->serviceClass()->is($serviceClass))->toBeTrue()
        ->and($serviceClass->fares()->count())->toBe(1)
        ->and($serviceClass->fares()->first()->id)->toBe($fare->id);
});
