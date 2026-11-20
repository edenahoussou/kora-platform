<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelSouteBudget;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelTransport;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

/** @return array<string, mixed> */
function assignmentFixture(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create([
        'agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true,
    ]);
    $destination = ParcelHub::query()->create([
        'agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true,
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => true,
    ]);
    $ops = User::factory()->create()->assignRole('operations_director');
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $receiver = User::factory()->create(['agency_id' => $destinationAgency->id])->assignRole('agency_agent');
    $conductor = User::factory()->create()->assignRole('conductor');
    $corridor->tariffs()->create(['base_amount' => 1500, 'return_amount' => 600, 'created_by' => $ops->id]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'registration_number' => 'ASSIGN-'.Str::random(8), 'brand' => 'KORA',
        'model' => 'Coach', 'seat_count' => 50, 'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->toDateString(), 'departure_time' => '12:00',
        'status' => DepartureStatus::Boarding,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);
    $second = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->addDay()->toDateString(), 'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);
    $reverse = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->toDateString(), 'departure_time' => '14:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $destinationStop->uuid], ['stop_uuid' => $originStop->uuid]],
    ]);
    $session = CashSession::factory()->create(['agent_id' => $agent->id, 'agency_id' => $originAgency->id]);
    $ledger = CashLedger::factory()->create(['cash_session_id' => $session->id]);

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency',
        'origin', 'destination', 'corridor', 'ops', 'agent', 'receiver', 'conductor',
        'bus', 'departure', 'second', 'reverse', 'session', 'ledger',
    );
}

/** @param array<string, mixed> $fixture */
function assignmentParcel(array $fixture, int $grams = 5000): Parcel
{
    return app(ParcelIntake::class)->createForDeposit($fixture['origin'], $fixture['destination'], $fixture['agent'], [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Assignment Sender',
        'sender_phone' => '+2290191000100',
        'recipient_name' => 'Assignment Recipient',
        'recipient_phone' => '+2290191000101',
        'contents' => 'Carton',
        'weight_grams' => $grams,
    ], true);
}

/** @param array<string, mixed> $fixture */
function payAndReady(TestCase $test, array $fixture, Parcel $parcel): void
{
    $web = '/parcels/data/'.$parcel->uuid;
    $test->actingAs($fixture['agent'], 'web')
        ->postJson($web.'/inspect', ['weight_grams' => $parcel->declared_grams, 'category' => 'allowed'])
        ->assertOk();
    $test->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $test->postJson($web.'/ready')->assertOk()->assertJsonPath('data.status', 'ready');
}

/** @param array<string, mixed> $fixture */
function approveCapacity(TestCase $test, array $fixture, Departure $departure, int $grams): void
{
    $test->actingAs($fixture['ops'], 'web')
        ->putJson('/parcels/data/departures/'.$departure->uuid.'/capacity', ['capacity_grams' => $grams])
        ->assertOk();
}

it('refuses ready and assignment until the deposit charge is settled', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    $this->actingAs($fixture['ops'], 'web');
    $web = '/parcels/data/'.$parcel->uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson($web.'/ready')->assertConflict();
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    $this->postJson($web.'/assign', ['departure_uuid' => $fixture['departure']->uuid])->assertConflict();
    expect($parcel->refresh()->status)->toBe('weighed');
});

it('never prepares or assigns a refused parcel', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    $this->actingAs($fixture['ops'], 'web');
    $web = '/parcels/data/'.$parcel->uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'weapon'])->assertOk()->assertJsonPath('data.status', 'refused');
    $this->postJson($web.'/ready')->assertConflict();
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    $this->actingAs($fixture['ops'], 'web')
        ->postJson($web.'/assign', ['departure_uuid' => $fixture['departure']->uuid])
        ->assertConflict();
    expect($parcel->refresh()->status)->toBe('refused')
        ->and($parcel->assignments()->count())->toBe(0);
});

it('rejects a departure whose stop order cannot serve the parcel route', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    payAndReady($this, $fixture, $parcel);
    approveCapacity($this, $fixture, $fixture['reverse'], 5000);
    $this->postJson('/parcels/data/'.$parcel->uuid.'/assign', ['departure_uuid' => $fixture['reverse']->uuid])
        ->assertConflict();
});

it('locks hold capacity so two parcels cannot overbook the same departure', function (): void {
    $fixture = assignmentFixture();
    $first = assignmentParcel($fixture, 5000);
    $second = assignmentParcel($fixture, 1000);
    payAndReady($this, $fixture, $first);
    payAndReady($this, $fixture, $second);
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    $this->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/'.$first->uuid.'/assign', ['departure_uuid' => $fixture['departure']->uuid])
        ->assertOk();
    expect($first->refresh()->status)->toBe('assigned');
    $this->postJson('/parcels/data/'.$second->uuid.'/assign', ['departure_uuid' => $fixture['departure']->uuid])
        ->assertConflict();

    $capacity = ParcelSouteBudget::query()->where('departure_id', $fixture['departure']->id)->firstOrFail()->capacity_grams;
    $reserved = ParcelAssignment::query()->where('departure_id', $fixture['departure']->id)
        ->whereNull('released_at')->join('parcels', 'parcels.id', '=', 'parcel_assignments.parcel_id')
        ->sum('parcels.weight_grams');
    expect($capacity)->toBe(5000)->and((int) $reserved)->toBe(5000);
});

it('moves a parcel to a single compatible departure and audits the release', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    payAndReady($this, $fixture, $parcel);
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    approveCapacity($this, $fixture, $fixture['second'], 5000);
    $this->actingAs($fixture['ops'], 'web');
    $web = '/parcels/data/'.$parcel->uuid;
    $this->postJson($web.'/assign', ['departure_uuid' => $fixture['departure']->uuid])->assertOk();
    $this->postJson($web.'/assign', ['departure_uuid' => $fixture['second']->uuid])->assertOk();

    expect($parcel->refresh()->status)->toBe('assigned')
        ->and($parcel->assignments()->whereNull('released_at')->count())->toBe(1)
        ->and($parcel->assignments()->whereNull('released_at')->firstOrFail()->departure_id)->toBe($fixture['second']->id)
        ->and($parcel->events()->where('kind', 'assignment_released')->count())->toBe(1);
});

it('releases capacity when the departure is cancelled and allows a new assignment', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    payAndReady($this, $fixture, $parcel);
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    approveCapacity($this, $fixture, $fixture['second'], 5000);
    $this->actingAs($fixture['ops'], 'web');
    $web = '/parcels/data/'.$parcel->uuid;
    $this->postJson($web.'/assign', ['departure_uuid' => $fixture['departure']->uuid])->assertOk();
    $fixture['departure']->update(['status' => DepartureStatus::Cancelled]);

    expect($parcel->refresh()->status)->toBe('ready')
        ->and($parcel->assignments()->whereNull('released_at')->count())->toBe(0)
        ->and($parcel->events()->where('kind', 'departure_cancelled')->count())->toBe(1);
    $this->postJson($web.'/assign', ['departure_uuid' => $fixture['second']->uuid])->assertOk();
});

it('exposes only compatible departures and server-derived hold capacity', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    payAndReady($this, $fixture, $parcel);
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    $this->getJson('/parcels/data/'.$parcel->uuid.'/assignment-options')
        ->assertOk()
        ->assertJsonPath('data.required_grams', 5000)
        ->assertJsonPath('data.departures.0.departure_uuid', $fixture['departure']->uuid)
        ->assertJsonPath('data.departures.0.eligible', true)
        ->assertJsonPath('data.departures.0.remaining_grams', 5000);
});

it('moves an assigned parcel to loaded then in transit on departure', function (): void {
    $fixture = assignmentFixture();
    $parcel = assignmentParcel($fixture);
    payAndReady($this, $fixture, $parcel);
    approveCapacity($this, $fixture, $fixture['departure'], 5000);
    $this->actingAs($fixture['ops'], 'web');
    $this->postJson('/parcels/data/'.$parcel->uuid.'/assign', ['departure_uuid' => $fixture['departure']->uuid])
        ->assertOk()->assertJsonPath('data.status', 'assigned');

    $terminal = TerminalP501::factory()->active()->create();
    app(ParcelTransport::class)->scan(
        $fixture['departure'], $fixture['conductor'], $terminal, $parcel->reference,
        $fixture['originStop']->uuid, true, now(), (string) Str::uuid(),
    );
    expect($parcel->refresh()->status)->toBe('loaded');

    $fixture['departure']->update(['status' => DepartureStatus::Departed, 'departed_at' => now()]);
    expect($parcel->refresh()->status)->toBe('in_transit');
});

it('prints a manifest sorted by stop with public references and QR only', function (): void {
    $fixture = assignmentFixture();
    $first = assignmentParcel($fixture, 5000);
    payAndReady($this, $fixture, $first);
    approveCapacity($this, $fixture, $fixture['departure'], 10000);
    $this->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/'.$first->uuid.'/assign', ['departure_uuid' => $fixture['departure']->uuid])
        ->assertOk();

    $manifest = $this->actingAs($fixture['ops'], 'web')
        ->getJson('/parcels/data/departures/'.$fixture['departure']->uuid.'/manifest')
        ->assertOk()
        ->assertJsonPath('data.summary.parcels', 1)
        ->assertJsonPath('data.parcels.0.reference', $first->reference)
        ->assertJsonPath('data.parcels.0.qr_payload', route('api.v1.parcels.tracking', ['reference' => $first->reference]))
        ->json('data');

    expect($manifest['version'])->toBeString()
        ->and($manifest['stops'])->toHaveCount(2)
        ->and($manifest['parcels'][0])->not->toHaveKey('assignment_uuid')
        ->and($manifest['parcels'][0])->not->toHaveKey('parcel_uuid');

    $this->get('/parcels/manifest/'.$fixture['departure']->uuid.'/print')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('parcel-manifest')
            ->where('manifest.summary.parcels', 1)
            ->where('manifest.parcels.0.reference', $first->reference));
});
