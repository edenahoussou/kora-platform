<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

/** @return array<string, mixed> */
function apiParcelScenario(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create(['agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true]);
    $destination = ParcelHub::query()->create(['agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true]);
    $corridor = ParcelCorridor::query()->create(['origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => true]);
    $ops = User::factory()->create()->assignRole('operations_director');
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $sender = User::factory()->create(['phone' => '+2290191000000'])->assignRole('passenger');
    $corridor->tariffs()->create(['base_amount' => 1500, 'return_amount' => 600, 'created_by' => $ops->id]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => Bus::query()->create(['registration_number' => 'API-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active'])->id,
        'conductor_id' => $ops->id,
        'departure_date' => now()->toDateString(),
        'departure_time' => '12:00',
        'status' => DepartureStatus::Boarding,
    ]);
    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => Bus::query()->create(['registration_number' => 'INTAKE-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active'])->id,
        'conductor_id' => $ops->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);
    $session = CashSession::factory()->create(['agent_id' => $agent->id, 'agency_id' => $originAgency->id]);
    $ledger = CashLedger::factory()->create(['cash_session_id' => $session->id]);

    return compact('corridor', 'agent', 'sender', 'ops', 'departure', 'ledger', 'originStop', 'destinationStop');
}

/** @return array<string, mixed> */
function apiParcelPayload(ParcelCorridor $corridor, array $overrides = []): array
{
    return $overrides + [
        'corridor_uuid' => $corridor->uuid,
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Expéditeur privé',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Destinataire privé',
        'recipient_phone' => '+2290191000001',
        'contents' => 'Livres',
        'weight_grams' => 5000,
    ];
}

function apiCreatePaidParcel($test, ParcelCorridor $corridor, User $agent, User $sender, array $overrides = []): Parcel
{
    Sanctum::actingAs($sender);
    $uuid = $test->postJson('/api/v1/parcels', apiParcelPayload($corridor, $overrides))
        ->assertCreated()->json('data.uuid');
    $test->actingAs($agent, 'web')->postJson('/parcels/data/'.$uuid.'/inspect', [
        'weight_grams' => 5000, 'category' => 'allowed',
    ])->assertOk();
    $test->postJson('/parcels/data/'.$uuid.'/pay', [
        'method' => 'cash',
        'amount' => 1500,
        'cash_received_amount' => 1500,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();

    return Parcel::query()->where('uuid', $uuid)->firstOrFail();
}

function apiVerifiedRecipient(string $phone): User
{
    $recipient = User::factory()->create(['phone' => $phone])->assignRole('passenger');
    $challenge = pendingOtpChallenge($phone, OtpPurpose::LoginNewDevice, '123456', user: $recipient);
    $challenge->update(['verified_at' => now(), 'consumed_at' => now()]);

    return $recipient;
}

it('exposes only the public tracking contract and keeps PII out of reference and shared links', function (): void {
    extract(apiParcelScenario());
    $parcel = apiCreatePaidParcel($this, $corridor, $agent, $sender);

    $tracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk();
    $public = $tracking->json('data');

    expect(array_keys($public))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ])->and($public)->not->toHaveKey('sender_name')
        ->not->toHaveKey('recipient_name')
        ->not->toHaveKey('sender_phone')
        ->not->toHaveKey('recipient_phone')
        ->not->toHaveKey('contents')
        ->not->toHaveKey('handoff_code')
        ->not->toHaveKey('share_hash');

    $tracking->assertJsonMissingPath('data.sender_name')
        ->assertJsonMissingPath('data.recipient_phone')
        ->assertJsonMissingPath('data.contents')
        ->assertJsonMissingPath('data.handoff_code');
    expect($tracking->getContent())->not->toContain('Expéditeur privé')
        ->not->toContain('Destinataire privé')
        ->not->toContain('+2290191000001');

    Sanctum::actingAs($sender);
    $share = $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/share')->assertOk();
    $path = $share->json('data.path');
    $token = basename((string) $path);
    expect($path)->toStartWith('/api/v1/parcels/shared/')->and(strlen($token))->toBe(48);

    $shared = $this->getJson($path)->assertOk();
    expect($shared->json('data'))->toBe($public);
    $this->deleteJson('/api/v1/parcels/'.$parcel->uuid.'/share')->assertNoContent();
    $this->getJson($path)->assertNotFound();

    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/share')->assertOk();
    $revoked = $this->getJson($path)->assertNotFound();
    expect($revoked->getContent())->not->toContain('Destinataire privé');
});

it('keeps holder creation idempotent and exposes only UUIDs and public references', function (): void {
    extract(apiParcelScenario());
    Sanctum::actingAs($sender);
    $payload = apiParcelPayload($corridor, ['idempotency_key' => '11111111-1111-4111-8111-111111111111']);

    $first = $this->postJson('/api/v1/parcels', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/parcels', $payload)->assertOk();

    expect($first->json('data.uuid'))->toBe($second->json('data.uuid'))
        ->and(Parcel::query()->count())->toBe(1)
        ->and($first->json('data'))->not->toHaveKey('id')
        ->and($second->json('data'))->not->toHaveKey('id');
});

it('does not serve the OPS handoff proof through the holder documents endpoint', function (): void {
    extract(apiParcelScenario());
    $parcel = apiCreatePaidParcel($this, $corridor, $agent, $sender);

    Sanctum::actingAs($sender);
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=handoff_proof')->assertNotFound();
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=receipt')
        ->assertOk()->assertJsonPath('data.kind', 'receipt')->assertJsonPath('data.fiscal_status', 'pending');
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=label')
        ->assertOk()->assertJsonPath('data.kind', 'label')
        ->assertJsonMissingPath('data.sender_name')
        ->assertJsonMissingPath('data.recipient_name');

    $recipient = apiVerifiedRecipient('+2290191000001');
    Sanctum::actingAs($recipient);
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=label')
        ->assertOk()->assertJsonPath('data.kind', 'label')
        ->assertJsonMissingPath('data.recipient_name');
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=receipt')->assertNotFound();
    $this->getJson('/api/v1/parcels/'.$parcel->uuid.'/documents?kind=handoff_proof')->assertNotFound();
});

it('authorizes incident reporting only for the holder or a verified recipient', function (): void {
    extract(apiParcelScenario());
    $parcel = apiCreatePaidParcel($this, $corridor, $agent, $sender);
    $stranger = User::factory()->create()->assignRole('passenger');

    Sanctum::actingAs($stranger);
    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'damage', 'description' => 'Carton enfoncé',
    ])->assertNotFound();

    Sanctum::actingAs($sender);
    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'damage', 'description' => 'Carton enfoncé',
    ])->assertCreated()->assertJsonStructure(['data' => ['uuid']]);

    $recipient = apiVerifiedRecipient('+2290191000001');
    Sanctum::actingAs($recipient);
    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'loss', 'description' => 'Contenu incomplet',
    ])->assertCreated()->assertJsonStructure(['data' => ['uuid']]);

    expect(ParcelIncident::query()->where('parcel_id', $parcel->id)->count())->toBe(2)
        ->and(ParcelIncident::query()->where('parcel_id', $parcel->id)->where('blocking', true)->count())->toBe(0);

    $tracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk();
    expect($tracking->getContent())->not->toContain('incident_reported')
        ->not->toContain('Carton enfoncé');
});

it('denies holder endpoints without authentication and hides a foreign parcel', function (): void {
    extract(apiParcelScenario());
    Sanctum::actingAs($sender);
    $uuid = $this->postJson('/api/v1/parcels', apiParcelPayload($corridor))->assertCreated()->json('data.uuid');

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/parcels')->assertUnauthorized();
    $this->getJson('/api/v1/parcels/'.$uuid)->assertUnauthorized();

    $stranger = User::factory()->create()->assignRole('passenger');
    Sanctum::actingAs($stranger);
    $this->getJson('/api/v1/parcels/'.$uuid)->assertNotFound();
    $this->getJson('/api/v1/parcels/'.$uuid.'/documents?kind=label')->assertNotFound();
});

it('enforces the public tracking rate limit', function (): void {
    extract(apiParcelScenario());
    $parcel = apiCreatePaidParcel($this, $corridor, $agent, $sender);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);
    for ($i = 0; $i < 15; $i++) {
        $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk();
    }

    $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Too Many Attempts.');
});
