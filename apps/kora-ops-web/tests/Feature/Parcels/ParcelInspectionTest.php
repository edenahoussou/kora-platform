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
use App\Modules\Parcels\Services\ParcelIntake;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function inspectionFixture(bool $partnerPoint = false, bool $supportsWeighing = true): array
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
            'supports_weighing' => $supportsWeighing,
        ]);
    } else {
        $originAgency = Agency::factory()->create(['country' => 'BJ']);
        $origin = ParcelHub::query()->create([
            'agency_id' => $originAgency->id,
            'stop_id' => $originStop->id,
            'enabled' => true,
            'supports_weighing' => $supportsWeighing,
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

    $bus = Bus::query()->create([
        'registration_number' => 'CI-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach',
        'seat_count' => 50, 'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'conductor_id' => User::factory()->create()->assignRole('conductor')->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);

    $parcel = app(ParcelIntake::class)->createForDeposit($origin, $destination, $agent, [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Inspection Sender',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Inspection Recipient',
        'recipient_phone' => '+2290191000001',
        'contents' => 'Books',
        'weight_grams' => 5000,
    ], true);

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency',
        'origin', 'destination', 'corridor', 'agent', 'bus', 'departure', 'parcel', 'point',
    );
}

function inspectAs($test, array $fixture, array $payload)
{
    return $test->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/inspect", $payload);
}

test('a parcel at the 30 kg limit is accepted with a new payable quote', function (): void {
    $fixture = inspectionFixture();

    $response = inspectAs($this, $fixture, [
        'weight_grams' => 30000,
        'category' => 'allowed',
    ])->assertOk()
        ->assertJsonPath('data.status', 'weighed')
        ->assertJsonPath('data.inspection.state', 'accepted')
        ->assertJsonPath('data.can_pay', true)
        ->assertJsonMissingPath('data.id');

    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);
    expect($parcel->weight_grams)->toBe(30000)
        ->and($parcel->inspection_category->value)->toBe('allowed')
        ->and($parcel->inspection_hub_id)->toBe($fixture['origin']->id)
        ->and($parcel->charges()->whereNull('superseded_at')->count())->toBe(1);
});

test('a weight above 30 kg is refused and audited without a payable charge', function (): void {
    $fixture = inspectionFixture();

    $response = inspectAs($this, $fixture, [
        'weight_grams' => 30001,
        'category' => 'allowed',
    ])->assertOk()
        ->assertJsonPath('data.status', 'refused')
        ->assertJsonPath('data.inspection.state', 'refused')
        ->assertJsonPath('data.can_pay', false);

    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);
    $event = $parcel->events()->where('kind', 'refused')->latest('id')->firstOrFail();

    expect($parcel->inspection_refusal_reason)->toContain('30000 g')
        ->and($event->details['reason_code'])->toBe('weight_above_limit')
        ->and($event->details['weight_grams'])->toBe(30001)
        ->and($event->actor_id)->toBe($fixture['agent']->id)
        ->and($event->hub_id)->toBe($fixture['origin']->id)
        ->and($parcel->charges()->whereNull('superseded_at')->count())->toBe(0);
});

test('forbidden categories are refused even when no weight is entered', function (): void {
    $fixture = inspectionFixture();

    inspectAs($this, $fixture, [
        'category' => 'flammable',
        'reason' => 'Fuel can found during opening.',
    ])->assertOk()->assertJsonPath('data.status', 'refused');

    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);
    $event = $parcel->events()->where('kind', 'refused')->latest('id')->firstOrFail();

    expect($parcel->inspection_category->value)->toBe('flammable')
        ->and($event->details['reason_code'])->toBe('forbidden_category')
        ->and($event->details['reason'])->toBe('Fuel can found during opening.');
});

test('declared cash above the configured threshold is refused', function (): void {
    $fixture = inspectionFixture();

    inspectAs($this, $fixture, [
        'weight_grams' => 5000,
        'category' => 'cash',
        'cash_value' => 500001,
    ])->assertOk()->assertJsonPath('data.status', 'refused');

    $event = Parcel::query()->findOrFail($fixture['parcel']->id)
        ->events()->where('kind', 'refused')->latest('id')->firstOrFail();

    expect($event->details['reason_code'])->toBe('cash_threshold_exceeded')
        ->and($event->details['cash_value'])->toBe(500001);
});

test('a corrected weight supersedes the previous quote and blocks its amount', function (): void {
    $fixture = inspectionFixture();

    inspectAs($this, $fixture, ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);
    $first = $parcel->charges()->whereNull('superseded_at')->sole();
    $firstTotal = $first->total;

    inspectAs($this, $fixture, ['weight_grams' => 6000, 'category' => 'allowed'])
        ->assertOk()
        ->assertJsonPath('data.weight_grams', 6000);

    $parcel->refresh();
    $active = $parcel->charges()->whereNull('superseded_at')->sole();
    $corrected = $parcel->events()->where('kind', 'weighed')->latest('id')->firstOrFail();

    expect($active->total)->toBeGreaterThan($firstTotal)
        ->and($first->fresh()->superseded_at)->not->toBeNull()
        ->and($corrected->details['corrected'])->toBeTrue()
        ->and($corrected->details['previous_charge_uuid'])->toBe($first->uuid);

    $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $firstTotal,
            'cash_received_amount' => $firstTotal,
        ])
        ->assertConflict();
});

test('a refused parcel cannot be paid, marked ready or assigned', function (): void {
    $fixture = inspectionFixture();
    inspectAs($this, $fixture, ['category' => 'weapon', 'reason' => 'Firearm found.'])->assertOk();

    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);

    $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => 1500,
            'cash_received_amount' => 1500,
        ])
        ->assertConflict();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/ready")
        ->assertConflict();

    $assigner = User::factory()->create()->assignRole('operations_director');

    $this->actingAs($assigner, 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/assign", [
            'departure_uuid' => $fixture['departure']->uuid,
        ])
        ->assertConflict();
});

test('inspection is refused when the deposit location has no weighing equipment', function (): void {
    $fixture = inspectionFixture(supportsWeighing: false);

    inspectAs($this, $fixture, ['weight_grams' => 5000, 'category' => 'allowed'])
        ->assertConflict();

    expect($fixture['parcel']->fresh()->status)->toBe('preregistered');
});

test('a partner point with weighing equipment can accept the parcel', function (): void {
    $fixture = inspectionFixture(partnerPoint: true);

    inspectAs($this, $fixture, ['weight_grams' => 5000, 'category' => 'allowed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'weighed')
        ->assertJsonPath('data.deposit_hub.kind', ParcelHub::KIND_PARTNER_POINT);

    expect($fixture['parcel']->fresh()->inspection_hub_id)->toBe($fixture['origin']->id);
});

test('an agent without the intake permission cannot inspect', function (): void {
    $fixture = inspectionFixture();
    $viewer = User::factory()->create()->assignRole('general_management');

    $this->actingAs($viewer, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/inspect", [
            'weight_grams' => 5000,
            'category' => 'allowed',
        ])
        ->assertForbidden();
});

test('inspection resources expose the same state in OPS and API contracts', function (): void {
    $fixture = inspectionFixture();
    inspectAs($this, $fixture, ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();

    $parcel = Parcel::query()->findOrFail($fixture['parcel']->id);
    $parcel->load(ParcelResource::relations());
    $data = (new ParcelResource($parcel))->toArray(request());

    expect($data['inspection_state'])->toBe('accepted')
        ->and($data['inspection']['category'])->toBe('allowed')
        ->and($data['inspection']['hub_uuid'])->toBe($fixture['origin']->uuid)
        ->and($data['inspection']['refusal_reason'])->toBeNull();
});
