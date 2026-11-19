<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelRouting;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function depositAttributionFixture(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create(['agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true]);
    $destination = ParcelHub::query()->create(['agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true]);
    $corridor = ParcelCorridor::query()->create(['origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => true]);
    $corridor->tariffs()->create(['base_amount' => 1500, 'return_amount' => 600, 'created_by' => User::factory()->create()->id]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');

    depositAttributionDeparture($line->id, $originStop->uuid, $destinationStop->uuid);

    return compact('line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency', 'origin', 'destination', 'corridor', 'agent');
}

function depositAttributionDeparture(int $lineId, string $originStopUuid, string $destinationStopUuid): Departure
{
    $bus = Bus::query()->create([
        'registration_number' => 'AT-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active',
    ]);

    return Departure::query()->create([
        'line_id' => $lineId,
        'bus_id' => $bus->id,
        'conductor_id' => User::factory()->create()->assignRole('conductor')->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStopUuid], ['stop_uuid' => $destinationStopUuid]],
    ]);
}

function depositIntakePayload(): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Sender', 'sender_phone' => '+2290191000000',
        'recipient_name' => 'Recipient', 'recipient_phone' => '+2290191000001',
        'contents' => 'Books', 'weight_grams' => 5000,
    ];
}

test('intake freezes the deposit hub and the selling agency snapshot', function (): void {
    $fixture = depositAttributionFixture();
    $parcel = app(ParcelIntake::class)->create($fixture['corridor'], $fixture['agent'], depositIntakePayload(), true);

    expect($parcel->deposit_hub_id)->toBe($fixture['origin']->id);
    expect($parcel->selling_agency_id)->toBe($fixture['originAgency']->id);

    $fixture['origin']->update(['archived_at' => now(), 'enabled' => false]);

    $stored = Parcel::query()->whereKey($parcel->id)->firstOrFail();
    expect($stored->deposit_hub_id)->toBe($fixture['origin']->id);
    expect($stored->selling_agency_id)->toBe($fixture['originAgency']->id);
});

test('intake is refused when the deposit hub is archived', function (): void {
    $fixture = depositAttributionFixture();
    $fixture['origin']->update(['archived_at' => now()]);

    expect(fn (): Parcel => app(ParcelIntake::class)->create($fixture['corridor'], $fixture['agent'], depositIntakePayload(), true))
        ->toThrow(HttpException::class);
});

test('a parcel can never be attached to a deposit hub other than the corridor origin', function (): void {
    $fixture = depositAttributionFixture();
    $foreignHub = ParcelHub::query()->create([
        'agency_id' => Agency::factory()->create(['country' => 'BJ'])->id,
        'stop_id' => $fixture['destinationStop']->id,
        'enabled' => true,
    ]);

    expect(fn () => app(ParcelRouting::class)->assertDepositMatchesCorridor($foreignHub, $fixture['corridor']))
        ->toThrow(HttpException::class);
});
