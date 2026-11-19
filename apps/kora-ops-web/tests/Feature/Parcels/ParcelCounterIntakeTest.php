<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Parcels\Http\Resources\ParcelResource;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelSouteBudget;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelIntake;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function counterIntakeFixture(bool $partnerPoint = false, bool $withDeparture = true): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $point = null;

    if ($partnerPoint) {
        $originAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
        $point = KoraPoint::factory()->create(['agency_id' => $originAgency->id]);
        $origin = ParcelHub::query()->create([
            'agency_id' => $originAgency->id,
            'kora_point_id' => $point->id,
            'kind' => ParcelHub::KIND_PARTNER_POINT,
            'stop_id' => $originStop->id,
            'enabled' => true,
        ]);
    } else {
        $originAgency = Agency::factory()->create(['country' => 'BJ']);
        $origin = ParcelHub::query()->create([
            'agency_id' => $originAgency->id,
            'stop_id' => $originStop->id,
            'enabled' => true,
        ]);
    }

    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
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
    $corridor->tariffs()->create([
        'base_amount' => 1500,
        'return_amount' => 600,
        'created_by' => User::factory()->create()->id,
    ]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $sender = User::factory()->create(['phone' => '+2290191000000'])->assignRole('passenger');

    if ($withDeparture) {
        counterDeparture($line->id, $originStop->uuid, $destinationStop->uuid);
    }

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency',
        'origin', 'destination', 'corridor', 'agent', 'sender', 'point',
    );
}

function counterDeparture(int $lineId, string $originStopUuid, string $destinationStopUuid, ?int $capacityGrams = null): Departure
{
    $bus = Bus::query()->create([
        'registration_number' => 'CI-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $lineId,
        'bus_id' => $bus->id,
        'conductor_id' => User::factory()->create()->assignRole('conductor')->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStopUuid], ['stop_uuid' => $destinationStopUuid]],
    ]);

    if ($capacityGrams !== null) {
        ParcelSouteBudget::query()->create([
            'departure_id' => $departure->id,
            'bus_id' => $departure->bus_id,
            'capacity_grams' => $capacityGrams,
        ]);
    }

    return $departure;
}

function counterIntakePayload(): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Counter Sender',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Counter Recipient',
        'recipient_phone' => '+2290191000001',
        'contents' => 'Books',
        'weight_grams' => 5000,
    ];
}

test('counter intake creates a preregistered parcel with a reference and frozen attribution', function (): void {
    $fixture = counterIntakeFixture();
    $parcel = app(ParcelIntake::class)->createForDeposit(
        $fixture['origin'], $fixture['destination'], $fixture['agent'], counterIntakePayload(), true,
    );

    expect($parcel->status)->toBe('preregistered');
    expect($parcel->reference)->toMatch('/^KC-\d{8}-\d{5}$/');
    expect($parcel->deposit_hub_id)->toBe($fixture['origin']->id);
    expect($parcel->selling_agency_id)->toBe($fixture['originAgency']->id);

    $parcel->load(ParcelResource::relations());
    $data = (new ParcelResource($parcel))->toArray(request());
    expect($data)->not->toHaveKey('id');
    expect($data['tracking_url'])->toBe(route('api.v1.parcels.tracking', ['reference' => $parcel->reference]));
    expect($data['qr_payload'])->toBe($data['tracking_url']);
    expect($data['deposit_hub']['kind'])->toBe(ParcelHub::KIND_AGENCY);
});

test('a partner point agent can register a direct deposit at its own hub', function (): void {
    $fixture = counterIntakeFixture(partnerPoint: true);
    $parcel = app(ParcelIntake::class)->createForDeposit(
        $fixture['origin'], $fixture['destination'], $fixture['agent'], counterIntakePayload(), true,
    );

    expect($parcel->deposit_hub_id)->toBe($fixture['origin']->id);
    expect($parcel->selling_agency_id)->toBe($fixture['originAgency']->id);
    expect($fixture['origin']->kind)->toBe(ParcelHub::KIND_PARTNER_POINT);
});

test('an agent of another agency cannot register a deposit at a foreign hub', function (): void {
    $fixture = counterIntakeFixture();
    $foreignAgent = User::factory()->create(['agency_id' => $fixture['destinationAgency']->id])->assignRole('agency_agent');

    expect(fn (): Parcel => app(ParcelIntake::class)->createForDeposit(
        $fixture['origin'], $fixture['destination'], $foreignAgent, counterIntakePayload(), true,
    ))->toThrow(HttpException::class);
});

test('a deposit location without a scheduled departure is refused', function (): void {
    $fixture = counterIntakeFixture(withDeparture: false);

    expect(fn (): Parcel => app(ParcelIntake::class)->createForDeposit(
        $fixture['origin'], $fixture['destination'], $fixture['agent'], counterIntakePayload(), true,
    ))->toThrow(HttpException::class);
});

test('a departure without enough soute capacity is refused', function (): void {
    $fixture = counterIntakeFixture(withDeparture: false);
    counterDeparture($fixture['line']->id, $fixture['originStop']->uuid, $fixture['destinationStop']->uuid, 1000);

    expect(fn (): Parcel => app(ParcelIntake::class)->createForDeposit(
        $fixture['origin'], $fixture['destination'], $fixture['agent'], counterIntakePayload(), true,
    ))->toThrow(HttpException::class);
});

test('replaying the same idempotency key returns the existing parcel', function (): void {
    $fixture = counterIntakeFixture();
    $payload = counterIntakePayload();
    $service = app(ParcelIntake::class);

    $first = $service->createForDeposit($fixture['origin'], $fixture['destination'], $fixture['agent'], $payload, true);
    $replay = $service->createForDeposit($fixture['origin'], $fixture['destination'], $fixture['agent'], $payload, true);

    expect($replay->is($first))->toBeTrue();
    expect(Parcel::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1);

    $changed = $payload;
    $changed['weight_grams'] = 6000;

    expect(fn (): Parcel => $service->createForDeposit($fixture['origin'], $fixture['destination'], $fixture['agent'], $changed, true))
        ->toThrow(HttpException::class);
});

test('the public intake endpoint requires an idempotency key and replays safely', function (): void {
    $fixture = counterIntakeFixture();
    Sanctum::actingAs($fixture['sender']);

    $payload = [
        'deposit_hub_uuid' => $fixture['origin']->uuid,
        'destination_hub_uuid' => $fixture['destination']->uuid,
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'API Sender',
        'recipient_name' => 'API Recipient',
        'recipient_phone' => '+2290191000001',
        'contents' => 'Books',
        'weight_grams' => 5000,
    ];

    $missingKey = $payload;
    unset($missingKey['idempotency_key']);
    $this->postJson('/api/v1/parcels', $missingKey)->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);

    $created = $this->postJson('/api/v1/parcels', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'preregistered')
        ->assertJsonMissingPath('data.id');
    expect($created->json('data.reference'))->toMatch('/^KC-\d{8}-\d{5}$/');
    expect($created->json('data.tracking_url'))->toContain('/api/v1/parcels/tracking/');

    $this->postJson('/api/v1/parcels', $payload)->assertOk()->assertJsonPath('data.uuid', $created->json('data.uuid'));

    $changed = $payload;
    $changed['weight_grams'] = 6000;
    $this->postJson('/api/v1/parcels', $changed)->assertConflict();

    expect(Parcel::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1);
});

test('the database rejects a duplicate idempotency key for the same agency', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Idempotency uniqueness is enforced on the pgsql connection.');
    }

    $fixture = counterIntakeFixture();
    $payload = counterIntakePayload();
    app(ParcelIntake::class)->createForDeposit($fixture['origin'], $fixture['destination'], $fixture['agent'], $payload, true);

    expect(fn (): Parcel => Parcel::query()->create([
        'corridor_id' => $fixture['corridor']->id,
        'deposit_hub_id' => $fixture['origin']->id,
        'selling_agency_id' => $fixture['origin']->agency_id,
        'idempotency_key' => $payload['idempotency_key'],
        'intake_fingerprint' => str_repeat('a', 64),
        'status' => 'preregistered',
        'sender_name' => 'Duplicate',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Duplicate',
        'recipient_phone' => '+2290191000001',
        'recipient_phone_hash' => ParcelAccess::phoneHash('+2290191000001'),
        'contents' => 'Books',
        'declared_grams' => 5000,
        'expires_at' => now()->addHour(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('the ops web intake route creates a counter parcel with reference and QR', function (): void {
    $fixture = counterIntakeFixture();
    $ops = User::factory()->create()->assignRole('operations_director');
    $payload = counterIntakePayload() + [
        'deposit_hub_uuid' => $fixture['origin']->uuid,
        'destination_hub_uuid' => $fixture['destination']->uuid,
    ];

    $created = $this->actingAs($ops, 'web')
        ->postJson('/parcels/data/intake', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'preregistered')
        ->assertJsonMissingPath('data.id');

    expect($created->json('data.reference'))->toMatch('/^KC-\d{8}-\d{5}$/');
    expect($created->json('data.qr_payload'))->toBe($created->json('data.tracking_url'));
    expect($created->json('data.tracking_url'))->toContain('/api/v1/parcels/tracking/');
    expect(Parcel::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1);
});

test('the ops web intake route requires the intake permission', function (): void {
    $fixture = counterIntakeFixture();
    $viewer = User::factory()->create()->assignRole('general_management');

    $this->actingAs($viewer, 'web')
        ->postJson('/parcels/data/intake', counterIntakePayload() + [
            'deposit_hub_uuid' => $fixture['origin']->uuid,
            'destination_hub_uuid' => $fixture['destination']->uuid,
        ])
        ->assertForbidden();
});
