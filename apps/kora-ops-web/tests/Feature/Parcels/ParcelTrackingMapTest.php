<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelEvent;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelIntake;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

/** @return array<string, mixed> */
function opsTrackingFixture(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();

    $originStop->update(['latitude' => 6.3654, 'longitude' => 2.4183]);
    $destinationStop->update(['latitude' => 9.3372, 'longitude' => 2.6303]);

    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);

    $origin = ParcelHub::query()->create([
        'agency_id' => $originAgency->id,
        'stop_id' => $originStop->id,
        'enabled' => true,
        'supports_weighing' => true,
    ]);
    $destination = ParcelHub::query()->create([
        'agency_id' => $destinationAgency->id,
        'stop_id' => $destinationStop->id,
        'enabled' => true,
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id,
        'destination_hub_id' => $destination->id,
        'enabled' => true,
    ]);

    $ops = User::factory()->create()->assignRole('operations_director');
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $conductor = User::factory()->create()->assignRole('conductor');

    $bus = Bus::query()->create([
        'registration_number' => 'TRACK-'.Str::random(8),
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'conductor_id' => $conductor->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [
            ['stop_uuid' => $originStop->uuid],
            ['stop_uuid' => $destinationStop->uuid],
        ],
    ]);

    $corridor->tariffs()->create([
        'base_amount' => 1500,
        'return_amount' => 600,
        'created_by' => $ops->id,
    ]);
    TaxRule::query()->create([
        'service_type' => 'parcel',
        'scope' => 'BJ',
        'tax_code' => 'COLIS-BJ',
        'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    $parcel = app(ParcelIntake::class)->createForDeposit($origin, $destination, $agent, [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Tracking Sender',
        'sender_phone' => '+2290191000100',
        'recipient_name' => 'Tracking Recipient',
        'recipient_phone' => '+2290191000101',
        'contents' => 'Carton suivi',
        'weight_grams' => 3000,
    ], true);

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency',
        'destinationAgency', 'origin', 'destination', 'corridor',
        'ops', 'agent', 'conductor', 'bus', 'departure', 'parcel',
    );
}

it('requires authentication for the ops tracking endpoint', function (): void {
    $fixture = opsTrackingFixture();

    $this->getJson('/parcels/data/'.$fixture['parcel']->uuid.'/tracking')
        ->assertUnauthorized();
});

it('forbids users without parcels.view from reading tracking', function (): void {
    $fixture = opsTrackingFixture();
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'web')
        ->getJson('/parcels/data/'.$fixture['parcel']->uuid.'/tracking')
        ->assertForbidden();
});

it('returns planned route, progress and public-safe events', function (): void {
    $fixture = opsTrackingFixture();

    /** @var Parcel $parcel */
    $parcel = $fixture['parcel'];

    $response = $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$parcel->uuid.'/tracking')
        ->assertOk();

    $payload = $response->json('data');

    expect($payload['reference'])->toBe($parcel->reference)
        ->and($payload['planned'])->toHaveCount(2)
        ->and($payload['planned'][0]['role'])->toBe('origin')
        ->and($payload['planned'][0]['city'])->toBe('Cotonou')
        ->and($payload['planned'][1]['role'])->toBe('destination')
        ->and($payload['planned'][1]['city'])->toBe('Parakou')
        ->and($payload['traveled'][0]['latitude'])->toBe(6.3654)
        ->and($payload['traveled'][0]['longitude'])->toBe(2.4183)
        ->and($payload['events'][0]['kind'])->toBe('preregistered')
        ->and($payload['events'][0]['public'])->toBeTrue()
        ->and($payload['progress_ratio'])->toBeGreaterThanOrEqual(0)
        ->and($payload['progress_ratio'])->toBeLessThanOrEqual(1);
});

it('does not expose personal data or handoff secrets', function (): void {
    $fixture = opsTrackingFixture();

    $response = $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$fixture['parcel']->uuid.'/tracking')
        ->assertOk();

    expect($response->json('data'))->not->toHaveKeys([
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'handoff_code',
        'share_hash',
    ]);

    $body = $response->getContent();

    expect($body)
        ->not->toContain('Tracking Sender')
        ->not->toContain('Tracking Recipient')
        ->not->toContain('+2290191000100')
        ->not->toContain('+2290191000101')
        ->not->toContain('Carton suivi');
});

it('computes progress from named stops even without coordinates', function (): void {
    $fixture = opsTrackingFixture();
    $fixture['originStop']->update(['latitude' => null, 'longitude' => null]);
    $fixture['destinationStop']->update(['latitude' => null, 'longitude' => null]);

    ParcelEvent::query()->create([
        'parcel_id' => $fixture['parcel']->id,
        'hub_id' => $fixture['destination']->id,
        'kind' => 'withdrawn',
        'channel' => 'ops',
        'observed_at' => now(),
    ]);

    $payload = $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$fixture['parcel']->uuid.'/tracking')
        ->assertOk()
        ->json('data');

    expect($payload['planned'])->toBe([])
        ->and($payload['progress_ratio'])->toEqual(1.0);
});

it('keeps the former public tracking views unavailable', function (): void {
    $this->get('/track/KC-20261119-27176')->assertNotFound();
    $this->get('/track/s/'.str_repeat('a', 48))->assertNotFound();
    $this->get('/track')->assertNotFound();
});
