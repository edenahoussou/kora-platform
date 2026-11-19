<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelSouteBudget;
use App\Modules\Parcels\Services\ParcelRouting;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function routingCorridor(bool $enabled = true): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create(['agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true]);
    $destination = ParcelHub::query()->create(['agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true]);
    $corridor = ParcelCorridor::query()->create(['origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => $enabled]);
    $corridor->tariffs()->create(['base_amount' => 1500, 'return_amount' => 600, 'created_by' => User::factory()->create()->id]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $ops = User::factory()->create()->assignRole('operations_director');

    return compact('line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency', 'origin', 'destination', 'corridor', 'ops');
}

function routingDeparture(array $fixture, string $date, DepartureStatus $status, bool $reverse = false): Departure
{
    $bus = Bus::query()->create([
        'registration_number' => 'RT-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active',
    ]);
    $conductor = User::factory()->create()->assignRole('conductor');

    return Departure::query()->create([
        'line_id' => $fixture['line']->id,
        'bus_id' => $bus->id,
        'conductor_id' => $conductor->id,
        'departure_date' => $date,
        'departure_time' => '08:00',
        'status' => $status,
        'stop_times' => $reverse
            ? [['stop_uuid' => $fixture['destinationStop']->uuid], ['stop_uuid' => $fixture['originStop']->uuid]]
            : [['stop_uuid' => $fixture['originStop']->uuid], ['stop_uuid' => $fixture['destinationStop']->uuid]],
    ]);
}

test('routing keeps only future scheduled departures serving both stops in order', function (): void {
    $fixture = routingCorridor();
    $routing = app(ParcelRouting::class);

    $eligible = routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Scheduled);
    routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Scheduled, reverse: true);
    routingDeparture($fixture, now()->subDay()->toDateString(), DepartureStatus::Scheduled);
    routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Cancelled);

    $departures = $routing->departures($fixture['origin'], $fixture['destination']);
    expect($departures)->toHaveCount(1);
    expect($departures->first()->is($eligible))->toBeTrue();
    expect($routing->filterOperationallyAvailable(collect([$fixture['corridor']])))->toHaveCount(1);
});

test('a corridor without an eligible departure is not proposed publicly', function (): void {
    $fixture = routingCorridor();
    routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Scheduled, reverse: true);

    $this->getJson('/api/v1/parcels/corridors')->assertSuccessful()
        ->assertJsonCount(0, 'data');

    $this->actingAs($fixture['ops'], 'web')
        ->getJson('/parcels/data/routing/departures?origin_hub_uuid='.$fixture['origin']->uuid.'&destination_hub_uuid='.$fixture['destination']->uuid)
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.departures')
        ->assertJsonPath('data.horizon_days', 30);
});

test('routing availability endpoint exposes eligible departures and soute capacity', function (): void {
    $fixture = routingCorridor();
    $departure = routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Scheduled);
    ParcelSouteBudget::query()->create(['departure_id' => $departure->id, 'bus_id' => $departure->bus_id, 'capacity_grams' => 30000]);

    $this->actingAs($fixture['ops'], 'web')
        ->getJson('/parcels/data/routing/departures?origin_hub_uuid='.$fixture['origin']->uuid.'&destination_hub_uuid='.$fixture['destination']->uuid)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.departures')
        ->assertJsonPath('data.departures.0.uuid', $departure->uuid)
        ->assertJsonPath('data.departures.0.capacity_grams', 30000)
        ->assertJsonPath('data.departures.0.reserved_grams', 0)
        ->assertJsonPath('data.departures.0.available_grams', 30000);
});

test('public corridor listing hides archived deposit locations', function (): void {
    $fixture = routingCorridor();
    routingDeparture($fixture, now()->addDay()->toDateString(), DepartureStatus::Scheduled);

    $this->getJson('/api/v1/parcels/corridors')->assertSuccessful()->assertJsonCount(1, 'data');

    $fixture['origin']->update(['archived_at' => now(), 'enabled' => false]);

    $this->getJson('/api/v1/parcels/corridors')->assertSuccessful()->assertJsonCount(0, 'data');
});

test('corridor activation is rejected when the origin stop does not precede the destination stop', function (): void {
    $fixture = routingCorridor(enabled: false);
    $fixture['corridor']->update(['enabled' => false]);

    $this->actingAs($fixture['ops'], 'web')->putJson('/parcels/data/corridors', [
        'origin_hub_uuid' => $fixture['destination']->uuid,
        'destination_hub_uuid' => $fixture['origin']->uuid,
        'enabled' => true,
        'wallet_cash_enabled' => false,
    ])->assertConflict();
});
