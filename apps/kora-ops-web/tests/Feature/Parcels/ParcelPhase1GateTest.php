<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use App\Modules\Parcels\Models\ParcelOutbox;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Payments\Models\Payment;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

/**
 * @return array<string, mixed>
 */
function phase1Fixture(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
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
    $receiver = User::factory()->create(['agency_id' => $destinationAgency->id])->assignRole('agency_agent');
    $manager = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_manager');
    $viewer = User::factory()->create()->assignRole('general_management');
    $conductor = User::factory()->create()->assignRole('conductor');

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

    $bus = Bus::query()->create([
        'registration_number' => 'GATE-'.Str::random(8),
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

    $session = CashSession::factory()->create([
        'agent_id' => $agent->id,
        'agency_id' => $originAgency->id,
        'business_date' => now()->toDateString(),
    ]);
    CashLedger::factory()->create(['cash_session_id' => $session->id]);

    $terminal = TerminalP501::factory()->active()->create([
        'assigned_user_id' => $conductor->id,
    ]);
    $conductorToken = $conductor->createToken('device:field', ['*'], now()->addDay())->plainTextToken;

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency',
        'origin', 'destination', 'corridor', 'ops', 'agent', 'receiver', 'manager',
        'viewer', 'conductor', 'departure', 'session', 'terminal', 'conductorToken',
    );
}

/** @param array<string, mixed> $fixture */
function phase1CreateParcel(array $fixture, string $key): Parcel
{
    $payload = [
        'deposit_hub_uuid' => $fixture['origin']->uuid,
        'destination_hub_uuid' => $fixture['destination']->uuid,
        'idempotency_key' => $key,
        'sender_name' => 'Gate Sender',
        'sender_phone' => '+2290191000200',
        'recipient_name' => 'Gate Recipient',
        'recipient_phone' => '+2290191000201',
        'contents' => 'Carton de test Gate',
        'weight_grams' => 5000,
    ];

    $response = test()->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/intake', $payload)
        ->assertCreated()
        ->assertJsonMissingPath('data.id');

    return Parcel::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
}

/** @param array<string, mixed> $fixture */
function phase1OpenTerminal(array $fixture): array
{
    $headers = [
        'Authorization' => 'Bearer '.$fixture['conductorToken'],
        'X-Terminal-Serial' => $fixture['terminal']->serial_number,
    ];

    Auth::forgetGuards();

    $response = test()->withHeaders($headers)
        ->postJson('/api/v1/ops-mobile/terminal/handshake', [
            'serial_number' => $fixture['terminal']->serial_number,
            'hardware_model' => 'SUNMI P2',
            'os_version' => 'Android 13',
            'app_version' => '2.4.1',
        ])
        ->assertOk()
        ->assertJsonPath('data.session.state', 'active');

    return $headers;
}

it('runs the complete direct-deposit lifecycle with audits and public status', function (): void {
    $fixture = phase1Fixture();
    $parcel = phase1CreateParcel($fixture, (string) Str::uuid());

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/inspect', [
            'weight_grams' => 5000,
            'category' => 'allowed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'weighed');

    $charge = $parcel->charges()->whereNull('superseded_at')->sole();

    $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$parcel->uuid.'/payment-options')
        ->assertOk()
        ->assertJsonPath('data.cash_session.uuid', $fixture['session']->uuid)
        ->assertJsonPath('data.methods.0.available', true);

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/pay', [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $charge->total,
            'cash_received_amount' => $charge->total,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed');

    $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$parcel->uuid.'/receipt')
        ->assertOk()
        ->assertJsonPath('data.receipt_status', 'provisional')
        ->assertJsonMissingPath('data.handoff_code');

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/ready')
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');

    $this->actingAs($fixture['ops'], 'web')
        ->putJson('/parcels/data/departures/'.$fixture['departure']->uuid.'/capacity', [
            'capacity_grams' => 5000,
        ])
        ->assertOk();

    $this->actingAs($fixture['manager'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/assign', [
            'departure_uuid' => $fixture['departure']->uuid,
        ])
        ->assertForbidden();

    $this->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/assign', [
            'departure_uuid' => $fixture['departure']->uuid,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'assigned');

    $manifest = $this->actingAs($fixture['ops'], 'web')
        ->getJson('/parcels/data/departures/'.$fixture['departure']->uuid.'/manifest')
        ->assertOk()
        ->assertJsonPath('data.summary.parcels', 1)
        ->assertJsonPath('data.parcels.0.reference', $parcel->reference)
        ->json('data');

    expect($manifest['parcels'][0])->not->toHaveKey('parcel_uuid')
        ->and($manifest['parcels'][0])->not->toHaveKey('assignment_uuid');

    $headers = phase1OpenTerminal($fixture);
    $loadAt = now();

    $this->withHeaders($headers)
        ->postJson('/api/v1/ops/parcels/departures/'.$fixture['departure']->uuid.'/scan', [
            'reference' => $parcel->reference,
            'stop_uuid' => $fixture['originStop']->uuid,
            'direction' => 'load',
            'operation_uuid' => (string) Str::uuid(),
            'observed_at' => $loadAt->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'loaded');

    $this->assertSame('loaded', $parcel->refresh()->status);
    $fixture['departure']->update([
        'status' => DepartureStatus::Departed,
        'departed_at' => now()->subMinute(),
    ]);
    expect($parcel->refresh()->status)->toBe('in_transit');

    $unloadAt = now();

    $this->withHeaders($headers)
        ->postJson('/api/v1/ops/parcels/departures/'.$fixture['departure']->uuid.'/scan', [
            'reference' => $parcel->reference,
            'stop_uuid' => $fixture['destinationStop']->uuid,
            'direction' => 'unload',
            'operation_uuid' => (string) Str::uuid(),
            'observed_at' => $unloadAt->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'arrived');

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/receive')
        ->assertOk()
        ->assertJsonPath('data.status', 'available');

    $availableTracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'available');
    $handoffHash = $parcel->refresh()->handoff_hash;
    $available = $availableTracking->json('data');
    expect(array_keys($available))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ]);
    foreach ($available['timeline'] as $event) {
        expect(array_keys($event))->toBe(['kind', 'observed_at', 'city']);
    }
    expect($availableTracking->getContent())
        ->not->toContain($parcel->recipient_phone)
        ->not->toContain($parcel->recipient_name)
        ->not->toContain((string) $handoffHash)
        ->not->toContain('Code de retrait');

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/release', [
            'identity_type' => 'national_id',
            'reason' => 'Override non autorisé pour ce rôle',
        ])
        ->assertForbidden();

    $codeMessage = ParcelOutbox::query()
        ->where('parcel_id', $parcel->id)
        ->where('kind', 'withdrawal_code')
        ->sole()
        ->payload['message'];
    preg_match('/\b([0-9]{6})\b/', $codeMessage, $matches);
    expect($matches[1] ?? null)->not->toBeNull();
    $code = $matches[1];

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/release', ['code' => '000000'])
        ->assertUnprocessable();

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/release', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.status', 'withdrawn');

    $parcel->refresh();
    expect($parcel->status)->toBe('withdrawn')
        ->and($parcel->handoff_hash)->toBeNull()
        ->and($parcel->custody_hub_id)->toBeNull()
        ->and($parcel->events()->where('kind', 'withdrawn')->count())->toBe(1)
        ->and($parcel->events()->pluck('kind')->all())->toContain(
            'preregistered', 'weighed', 'registered', 'ready', 'assigned',
            'loaded', 'in_transit', 'unloaded', 'available', 'withdrawn',
        );

    $withdrawnTracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'withdrawn');
    expect($withdrawnTracking->getContent())
        ->not->toContain($parcel->recipient_phone)
        ->not->toContain($parcel->recipient_name)
        ->not->toContain($code)
        ->not->toContain($parcel->uuid);
});

it('never lets a refused parcel be paid, assigned, prepared or handed over', function (): void {
    $fixture = phase1Fixture();
    $parcel = phase1CreateParcel($fixture, (string) Str::uuid());

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/inspect', [
            'weight_grams' => 5000,
            'category' => 'weapon',
            'reason' => 'Objet interdit constaté à l’inspection.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'refused');

    $parcel = $parcel->refresh();
    $charge = $parcel->charges()->whereNull('superseded_at')->first();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/pay', [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => 1500,
            'cash_received_amount' => 1500,
        ])
        ->assertConflict();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/ready')
        ->assertConflict();

    $this->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/assign', [
            'departure_uuid' => $fixture['departure']->uuid,
        ])
        ->assertConflict();

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/receive')
        ->assertConflict();

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/release', ['code' => '123456'])
        ->assertConflict();

    expect($parcel->status)->toBe('refused')
        ->and($parcel->handoff_hash)->toBeNull()
        ->and($charge)->toBeNull()
        ->and($parcel->assignments()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and($parcel->events()->where('kind', 'refused')->count())->toBe(1);
});

it('replays creation, payment, notifications and incidents without duplicates', function (): void {
    $fixture = phase1Fixture();
    $key = (string) Str::uuid();
    $parcel = phase1CreateParcel($fixture, $key);

    $this->actingAs($fixture['ops'], 'web')
        ->postJson('/parcels/data/intake', [
            'deposit_hub_uuid' => $fixture['origin']->uuid,
            'destination_hub_uuid' => $fixture['destination']->uuid,
            'idempotency_key' => $key,
            'sender_name' => 'Gate Sender',
            'sender_phone' => '+2290191000200',
            'recipient_name' => 'Gate Recipient',
            'recipient_phone' => '+2290191000201',
            'contents' => 'Carton de test Gate',
            'weight_grams' => 5000,
        ])
        ->assertOk()
        ->assertJsonPath('data.uuid', $parcel->uuid);

    expect(Parcel::query()->where('idempotency_key', $key)->count())->toBe(1);

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/inspect', [
            'weight_grams' => 5000,
            'category' => 'allowed',
        ])
        ->assertOk();

    $charge = $parcel->charges()->whereNull('superseded_at')->sole();
    $paymentKey = (string) Str::uuid();
    $payload = [
        'idempotency_key' => $paymentKey,
        'method' => 'cash',
        'amount' => $charge->total,
        'cash_received_amount' => $charge->total,
    ];

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/pay', $payload)
        ->assertSuccessful();
    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/pay', $payload)
        ->assertSuccessful();

    expect(Payment::query()->count())->toBe(1)
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1)
        ->and($charge->fresh()->settled_payment_id)->not->toBeNull();

    $notifications = app(ParcelNotifications::class);
    $notifications->milestone($parcel->refresh(), 'ready');
    $notifications->milestone($parcel->refresh(), 'ready');

    expect(ParcelOutbox::query()->where('parcel_id', $parcel->id)->where('kind', 'ready')->count())->toBe(1);

    $incidentKey = (string) Str::uuid();
    $incidentPayload = [
        'kind' => 'damage',
        'description' => 'Carton enfoncé pendant le test Gate.',
        'blocking' => true,
        'idempotency_key' => $incidentKey,
    ];

    $this->actingAs($fixture['ops'], 'web');
    $created = $this->postJson('/parcels/data/'.$parcel->uuid.'/incidents', $incidentPayload)
        ->assertCreated()
        ->assertJsonPath('data.blocking', true);
    $this->postJson('/parcels/data/'.$parcel->uuid.'/incidents', $incidentPayload)
        ->assertOk()
        ->assertJsonPath('data.uuid', $created->json('data.uuid'));

    expect(ParcelIncident::query()->count())->toBe(1)
        ->and(ParcelOutbox::query()->where('parcel_id', $parcel->id)->where('kind', 'incident')->count())->toBe(1);
});

it('keeps sensitive configuration, assignment, override and finance behind the right permissions', function (): void {
    $fixture = phase1Fixture();
    $parcel = phase1CreateParcel($fixture, (string) Str::uuid());

    $this->actingAs($fixture['viewer'], 'web')
        ->putJson('/parcels/data/settings', [
            'parcelFirstReminderDays' => 30,
        ])
        ->assertForbidden();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/inspect', [
            'weight_grams' => 5000,
            'category' => 'allowed',
        ])
        ->assertOk();

    $charge = $parcel->charges()->whereNull('superseded_at')->sole();
    $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/pay', [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $charge->total,
            'cash_received_amount' => $charge->total,
        ])
        ->assertSuccessful();

    $this->actingAs($fixture['agent'], 'web')
        ->getJson('/parcels/data/'.$parcel->uuid.'/payments')
        ->assertForbidden();

    $this->actingAs($fixture['viewer'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/ready')
        ->assertForbidden();

    $this->actingAs($fixture['manager'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/assign', [
            'departure_uuid' => $fixture['departure']->uuid,
        ])
        ->assertForbidden();

    $parcel->update([
        'status' => 'available',
        'custody_hub_id' => $fixture['destination']->id,
        'available_at' => now(),
        'return_due_at' => now()->addDays(30),
        'handoff_hash' => bcrypt('123456'),
    ]);

    $this->actingAs($fixture['receiver'], 'web')
        ->postJson('/parcels/data/'.$parcel->uuid.'/release', [
            'identity_type' => 'national_id',
            'reason' => 'Tentative sans permission override',
        ])
        ->assertForbidden();
});

it('exports supervised aggregates and publishes an OpenAPI contract without PII', function (): void {
    $fixture = phase1Fixture();
    $parcel = phase1CreateParcel($fixture, (string) Str::uuid());
    $parcel->update(['handoff_hash' => bcrypt('654321')]);
    $parcel->incidents()->create([
        'kind' => 'damage',
        'description' => 'Description privée à ne jamais exporter',
        'blocking' => true,
    ]);

    $this->actingAs($fixture['ops'], 'web')
        ->get('/parcels/data/supervision/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $response = $this->actingAs($fixture['ops'], 'web')
        ->get('/parcels/data/supervision/export');
    $content = $response->streamedContent();
    expect($content)->toContain('Section,Type,Libellé,Valeur,Montant');

    expect($content)
        ->not->toContain($parcel->sender_phone)
        ->not->toContain($parcel->recipient_phone)
        ->not->toContain($parcel->sender_name)
        ->not->toContain($parcel->recipient_name)
        ->not->toContain('654321')
        ->not->toContain('Description privée');

    $openApi = Yaml::parseFile(base_path('../../docs/mintlify/api/openapi.yaml'));
    $trackingMedia = $openApi['paths']['/api/v1/parcels/tracking/{reference}']['get']['responses'][200]['content']['application/json'];
    $public = ($trackingMedia['example'] ?? $trackingMedia['schema']['example'])['data'];

    expect(array_keys($public))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ]);
    expect(array_keys($openApi['components']['schemas']['ParcelTrackingResponse']['properties']['data']['properties']))
        ->toBe(array_keys($public));

    expect($public)->not->toHaveKey('sender_name')
        ->not->toHaveKey('recipient_name')
        ->not->toHaveKey('sender_phone')
        ->not->toHaveKey('recipient_phone')
        ->not->toHaveKey('contents')
        ->not->toHaveKey('handoff_code')
        ->not->toHaveKey('handoff_hash')
        ->not->toHaveKey('provider_payload');

    foreach (['/api/v1/parcels/{parcel_uuid}/payments', '/api/v1/parcels/{parcel_uuid}/documents'] as $path) {
        expect($openApi['paths'])->toHaveKey($path);
    }
});
