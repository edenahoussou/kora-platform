<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Modules\Parcels\Data\ParcelEventKind;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use App\Modules\Parcels\Models\ParcelOutbox;
use App\Modules\Parcels\Services\ParcelHandoff;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Settings\Settings\OperationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

/**
 * @return array{origin: ParcelHub, destination: ParcelHub, corridor: ParcelCorridor, sender: User, ops: User}
 */
function lotIScenario(): array
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
        'supports_weighing' => true,
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id,
        'destination_hub_id' => $destination->id,
        'enabled' => true,
        'wallet_cash_enabled' => true,
    ]);
    $corridor->tariffs()->create([
        'base_amount' => 1500,
        'return_amount' => 600,
        'created_by' => User::factory()->create()->id,
    ]);
    $sender = User::factory()->create(['phone' => '+2290191000000'])->assignRole('passenger');
    $ops = User::factory()->create()->assignRole('operations_director');

    return compact('origin', 'destination', 'corridor', 'sender', 'ops', 'line');
}

/** @param array<string, mixed> $overrides */
function lotIParcel(array $scenario, string $status = 'arrived', array $overrides = []): Parcel
{
    return Parcel::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'reference' => 'KC-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'corridor_id' => $scenario['corridor']->id,
        'deposit_hub_id' => $scenario['origin']->id,
        'custody_hub_id' => $scenario['destination']->id,
        'sender_user_id' => $scenario['sender']->id,
        'status' => $status,
        'sender_name' => 'Client Lot I',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Destinataire Lot I',
        'recipient_phone' => '+2290191000001',
        'recipient_phone_hash' => hash('sha256', 'lot-i-recipient'),
        'contents' => 'Documents Lot I',
        'declared_grams' => 2000,
        'weight_grams' => 2000,
        'expires_at' => now()->addDays(2),
        'available_at' => $status === 'available' ? now() : null,
        'return_due_at' => $status === 'available' ? now()->subDay() : null,
        'handoff_hash' => in_array($status, ['available', 'returned'], true) ? bcrypt('123456') : null,
    ], $overrides));
}

it('deduplicates outbox entries and sends a notification only once across replays', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'registered');
    $phone = $parcel->sender_phone;
    $message = 'Message Lot I';
    $notifications = app(ParcelNotifications::class);

    $notifications->enqueue($parcel, 'test:key', 'registered', $phone, $message);
    $notifications->enqueue($parcel, 'test:key', 'registered', $phone, $message);

    expect(ParcelOutbox::query()->count())->toBe(1);
    $outbox = ParcelOutbox::query()->firstOrFail();
    expect(DB::table('parcel_outboxes')->value('payload'))
        ->not->toContain($phone)
        ->not->toContain($message)
        ->and($outbox->toArray())->not->toHaveKey('payload')
        ->not->toHaveKey('claim_token');

    $this->mock(WhatsAppMessageDispatcher::class, function (MockInterface $mock) use ($phone, $message): void {
        $mock->shouldReceive('send')->once()->with($phone, $message)
            ->andReturn(['provider' => 'genuka', 'message_id' => 'wa-1', 'fallback_error' => null]);
    });
    Http::fake();

    $notifications->sendPending(app(WhatsAppMessageDispatcher::class), app(ConexteoSmsClient::class));
    $notifications->sendPending(app(WhatsAppMessageDispatcher::class), app(ConexteoSmsClient::class));
    Http::assertNothingSent();

    $outbox->refresh();
    expect($outbox->completed_at)->not->toBeNull()
        ->and($outbox->attempts)->toBe(0)
        ->and($outbox->delivered_channel)->toBe('whatsapp')
        ->and($outbox->provider_message_id)->toBe('wa-1')
        ->and($outbox->claim_token)->toBeNull();
});

it('does not resend a message already claimed by another worker', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'registered');
    $notifications = app(ParcelNotifications::class);
    $notifications->enqueue($parcel, 'inflight:key', 'registered', $parcel->sender_phone, 'In-flight message');

    $outbox = ParcelOutbox::query()->firstOrFail();
    $outbox->update(['claimed_at' => now(), 'claim_token' => (string) Str::uuid()]);

    $this->mock(WhatsAppMessageDispatcher::class)->shouldNotReceive('send');
    Http::fake();

    $notifications->sendPending(app(WhatsAppMessageDispatcher::class), app(ConexteoSmsClient::class));

    Http::assertNothingSent();
    expect($outbox->refresh()->completed_at)->toBeNull()
        ->and($outbox->attempts)->toBe(0)
        ->and($outbox->claim_token)->not->toBeNull();
});

it('retries a failed notification without storing provider error text', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'registered');
    $phone = $parcel->sender_phone;
    $message = 'Retry message';
    $notifications = app(ParcelNotifications::class);
    $notifications->enqueue($parcel, 'retry:key', 'registered', $phone, $message);

    $this->mock(WhatsAppMessageDispatcher::class, function (MockInterface $mock) use ($phone, $message): void {
        $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('provider failed for '.$phone));
        $mock->shouldReceive('send')->once()->with($phone, $message)
            ->andReturn(['provider' => 'genuka', 'message_id' => 'wa-2', 'fallback_error' => null]);
    });
    Http::fake(['api.conexteo.com/*' => Http::response([], 500)]);

    $notifications->sendPending(app(WhatsAppMessageDispatcher::class), app(ConexteoSmsClient::class));
    $outbox = ParcelOutbox::query()->firstOrFail();
    expect($outbox->attempts)->toBe(1)
        ->and($outbox->completed_at)->toBeNull()
        ->and($outbox->last_error_code)->toBe('delivery_failed')
        ->and(DB::table('parcel_outboxes')->value('payload'))->not->toContain($phone);

    $outbox->update(['due_at' => now()]);
    $notifications->sendPending(app(WhatsAppMessageDispatcher::class), app(ConexteoSmsClient::class));

    $outbox->refresh();
    expect($outbox->completed_at)->not->toBeNull()
        ->and($outbox->attempts)->toBe(1)
        ->and($outbox->last_error_code)->toBeNull()
        ->and($outbox->delivered_channel)->toBe('whatsapp');
});

it('keeps the public timeline on the catalog whitelist and hides incident details', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'arrived');
    $incident = $parcel->incidents()->create([
        'kind' => 'damage',
        'description' => 'Téléphone '.$parcel->sender_phone.' — emballage ouvert',
        'blocking' => true,
        'opened_by_id' => $scenario['ops']->id,
    ]);
    $parcel->record(ParcelEventKind::Registered->value, $scenario['ops'], $scenario['origin']);
    $parcel->record(ParcelEventKind::IncidentOpened->value, $scenario['ops'], $scenario['destination'], details: [
        'incident_uuid' => $incident->uuid,
        'reason' => 'Téléphone '.$parcel->sender_phone,
    ]);

    expect(ParcelEventKind::publicTimeline())
        ->toContain(ParcelEventKind::ReturnTransit->value)
        ->not->toContain(ParcelEventKind::IncidentOpened->value)
        ->not->toContain(ParcelEventKind::IncidentReported->value)
        ->not->toContain(ParcelEventKind::PaymentReconciliationRequired->value)
        ->not->toContain(ParcelEventKind::TrackingLinkIssued->value);
    expect(fn () => $parcel->record('unknown_kind'))->toThrow(InvalidArgumentException::class);

    $tracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk();
    expect($tracking->json('data.status'))->toBe('incident')
        ->and(collect($tracking->json('data.timeline'))->pluck('kind')->all())
        ->not->toContain(ParcelEventKind::IncidentOpened->value)
        ->not->toContain(ParcelEventKind::IncidentReported->value);
    expect($tracking->getContent())
        ->not->toContain($parcel->sender_phone)
        ->not->toContain('emballage ouvert')
        ->not->toContain('incident_uuid');
});

it('qualifies client and OPS incidents, records resolution audit and restores public status', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'arrived');
    Sanctum::actingAs($scenario['sender']);

    $key = (string) Str::uuid();
    $client = $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'damage',
        'description' => 'Carton enfoncé',
        'blocking' => true,
        'idempotency_key' => $key,
    ])->assertCreated()->assertJsonPath('data.blocking', false);
    $clientUuid = $client->json('data.uuid');

    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'damage',
        'description' => 'Carton enfoncé',
        'blocking' => true,
        'idempotency_key' => $key,
    ])->assertOk()->assertJsonPath('data.uuid', $clientUuid);

    expect(ParcelIncident::query()->where('blocking', false)->count())->toBe(1)
        ->and($parcel->events()->where('kind', 'incident_reported')->count())->toBe(1)
        ->and(ParcelOutbox::query()->where('kind', 'incident')->count())->toBe(1);
    $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk()->assertJsonPath('data.status', 'arrived');

    $this->actingAs($scenario['ops'], 'web');
    $opsKey = (string) Str::uuid();
    $ops = $this->postJson('/parcels/data/'.$parcel->uuid.'/incidents', [
        'kind' => 'custody_discrepancy',
        'description' => 'Colis introuvable au débarquement',
        'blocking' => true,
        'idempotency_key' => $opsKey,
    ])->assertCreated()->assertJsonPath('data.blocking', true);
    $opsUuid = $ops->json('data.uuid');

    $this->postJson('/parcels/data/'.$parcel->uuid.'/incidents', [
        'kind' => 'custody_discrepancy',
        'description' => 'Colis introuvable au débarquement',
        'blocking' => true,
        'idempotency_key' => $opsKey,
    ])->assertOk()->assertJsonPath('data.uuid', $opsUuid);
    expect(ParcelIncident::query()->where('blocking', true)->count())->toBe(1);

    $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk()->assertJsonPath('data.status', 'incident');

    $this->postJson('/parcels/data/'.$parcel->uuid.'/incidents/'.$opsUuid.'/resolve', [
        'reason' => 'Colis retrouvé et remis en custody',
    ])->assertNoContent();

    $blocking = ParcelIncident::query()->where('uuid', $opsUuid)->firstOrFail();
    expect($blocking->resolved_at)->not->toBeNull()
        ->and($blocking->resolved_by_id)->toBe($scenario['ops']->id)
        ->and($blocking->resolution_reason)->toBe('Colis retrouvé et remis en custody')
        ->and($blocking->opened_by_id)->toBe($scenario['ops']->id);

    $this->getJson('/parcels/data/'.$parcel->uuid.'/incidents/'.$opsUuid)
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolved_by.name', $scenario['ops']->name)
        ->assertJsonPath('data.resolution_reason', 'Colis retrouvé et remis en custody')
        ->assertJsonCount(2, 'data.timeline');
    $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk()->assertJsonPath('data.status', 'arrived');
});

it('rate limits client incident declarations', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'arrived');
    Sanctum::actingAs($scenario['sender']);

    foreach (range(1, 5) as $index) {
        $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
            'kind' => 'damage',
            'description' => 'Signalement '.$index,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
    }

    $this->postJson('/api/v1/parcels/'.$parcel->uuid.'/incidents', [
        'kind' => 'damage',
        'description' => 'Signalement 6',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(429)->assertJsonPath('message', 'Too Many Attempts.');
});

it('exports incident counters without identity or incident text', function (): void {
    $scenario = lotIScenario();
    $open = lotIParcel($scenario, 'arrived');
    $resolved = lotIParcel($scenario, 'arrived');
    $open->incidents()->create([
        'kind' => 'damage',
        'description' => 'Client Lot I '.$open->sender_phone.' — contenu visible',
        'blocking' => true,
        'opened_by_id' => $scenario['ops']->id,
    ]);
    $incident = $resolved->incidents()->create([
        'kind' => 'loss',
        'description' => 'Dossier résolu',
        'blocking' => false,
        'opened_by_id' => $scenario['ops']->id,
    ]);
    $incident->update([
        'resolved_at' => now(),
        'resolved_by_id' => $scenario['ops']->id,
        'resolution_reason' => 'Retrouvé',
    ]);

    $response = $this->actingAs($scenario['ops'], 'web')->get('/parcels/data/supervision/export')->assertOk();
    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $csv), fn (string $line): bool => str_starts_with($line, 'Incidents,')));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toContain('Ouverts')->toContain('Tous')->toContain('1')
        ->and($lines[1])->toContain('Ouverts')->toContain('Bloquants')->toContain('1')
        ->and($lines[2])->toContain('Résolus aujourd’hui')->toContain('1')
        ->and($csv)->not->toContain($open->sender_phone)
        ->not->toContain('Client Lot I')
        ->not->toContain('contenu visible')
        ->not->toContain('Dossier résolu');
});

it('advances due handoffs idempotently across replays', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'available');
    $handoff = app(ParcelHandoff::class);

    $handoff->advanceDueParcels();
    $handoff->advanceDueParcels();

    expect($parcel->refresh()->status)->toBe('return_pending')
        ->and($parcel->events()->where('kind', 'return_pending')->count())->toBe(1)
        ->and(ParcelOutbox::query()->where('kind', 'return_pending')->count())->toBe(1);
});

it('schedules reminders from the audited runtime windows', function (): void {
    $scenario = lotIScenario();
    $parcel = lotIParcel($scenario, 'arrived');
    app(OperationsSettings::class)->fill([
        'parcelFirstReminderDays' => 2,
        'parcelSecondReminderDays' => 5,
        'parcelReturnDays' => 20,
    ])->save();

    $receiver = User::factory()->create(['agency_id' => $scenario['destination']->agency_id])->assignRole('agency_manager');
    app(ParcelHandoff::class)->receive($parcel, $receiver);

    $reminders = ParcelOutbox::query()->where('kind', 'reminder')->orderBy('due_at')->get();
    expect($reminders)->toHaveCount(2)
        ->and($reminders[0]->due_at->isSameDay(now()->addDays(2)))->toBeTrue()
        ->and($reminders[1]->due_at->isSameDay(now()->addDays(5)))->toBeTrue()
        ->and($parcel->refresh()->return_due_at?->isSameDay(now()->addDays(20)))->toBeTrue();
});
