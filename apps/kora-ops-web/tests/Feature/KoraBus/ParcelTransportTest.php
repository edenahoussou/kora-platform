<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\EmecfInvoiceSnapshotService;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelOutbox;
use App\Modules\Parcels\Services\ParcelHandoff;
use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Parcels\Services\ParcelRevenue;
use App\Modules\Parcels\Services\ParcelTransport;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Sync\Actions\ProcessSyncBatchAction;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Services\EmecfService;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

function parcelScenario(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create(['agency_id' => $originAgency->id, 'stop_id' => $originStop->id, 'enabled' => true]);
    $destination = ParcelHub::query()->create(['agency_id' => $destinationAgency->id, 'stop_id' => $destinationStop->id, 'enabled' => true]);
    $corridor = ParcelCorridor::query()->create(['origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'enabled' => true]);
    $ops = User::factory()->create()->assignRole('operations_director');
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $receiver = User::factory()->create(['agency_id' => $destinationAgency->id])->assignRole('agency_agent');
    $conductor = User::factory()->create()->assignRole('conductor');
    $sender = User::factory()->create(['phone' => '+2290191000000'])->assignRole('passenger');
    $corridor->tariffs()->create(['base_amount' => 1500, 'return_amount' => 600, 'created_by' => $ops->id]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => Bus::query()->create(['registration_number' => 'COLIS-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active'])->id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->toDateString(), 'departure_time' => '12:00', 'status' => DepartureStatus::Boarding,
    ]);
    Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => Bus::query()->create(['registration_number' => 'INTAKE-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 50, 'status' => 'active'])->id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->addDay()->toDateString(), 'departure_time' => '08:00', 'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);
    $session = CashSession::factory()->create(['agent_id' => $agent->id, 'agency_id' => $originAgency->id]);
    $ledger = CashLedger::factory()->create(['cash_session_id' => $session->id]);

    return compact('corridor', 'agent', 'receiver', 'sender', 'ops', 'conductor', 'departure', 'originStop', 'destinationStop', 'ledger');
}

function parcelIntakePayload(ParcelCorridor $corridor): array
{
    return [
        'corridor_uuid' => $corridor->uuid, 'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Expéditeur privé', 'sender_phone' => '+2290191000000',
        'recipient_name' => 'Destinataire privé', 'recipient_phone' => '+2290191000001',
        'contents' => 'Livres', 'weight_grams' => 5000,
    ];
}

it('prices a started weight bracket and rejects a closed corridor', function (int $grams, int $total): void {
    $corridor = parcelScenario()['corridor'];
    $this->postJson('/api/v1/parcels/quote', ['corridor_uuid' => $corridor->uuid, 'weight_grams' => $grams])
        ->assertOk()->assertJsonPath('data.total', $total)->assertJsonPath('data.estimated', true);
    $corridor->update(['enabled' => false]);
    $this->postJson('/api/v1/parcels/quote', ['corridor_uuid' => $corridor->uuid, 'weight_grams' => $grams])->assertConflict();
})->with([[5000, 1500], [5001, 3000], [10000, 3000], [30000, 9000]]);

it('keeps the payment, custody, offline replay and withdrawal consistent across the hub to hub flow', function (): void {
    extract(parcelScenario());
    Sanctum::actingAs($sender);
    $uuid = $this->postJson('/api/v1/parcels', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $web = '/parcels/data/'.$uuid;
    $this->actingAs($receiver, 'web')->postJson($web.'/inspect', ['weight_grams' => 5001, 'category' => 'allowed'])->assertForbidden();
    $this->actingAs($agent, 'web')->postJson($web.'/inspect', ['weight_grams' => 5001, 'category' => 'allowed'])
        ->assertOk()->assertJsonPath('data.quote.total', 3000);
    $payment = ['method' => 'cash', 'amount' => 3000, 'cash_received_amount' => 3000, 'idempotency_key' => (string) Str::uuid()];
    $this->postJson($web.'/pay', [...$payment, 'amount' => 1500])->assertConflict();
    $this->postJson($web.'/pay', $payment)->assertSuccessful()->assertJsonPath('data.status', 'confirmed');
    $referenceAtIntake = $parcel->reference;
    $this->postJson($web.'/pay', $payment)->assertSuccessful();
    expect(CashMovement::query()->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1)
        ->and($parcel->refresh()->status)->toBe('registered')
        ->and($parcel->reference)->toBe($referenceAtIntake);
    $tracking = $this->getJson('/api/v1/parcels/tracking/'.$parcel->reference)->assertOk()
        ->assertJsonPath('data.status', 'registered')
        ->assertJsonPath('data.origin', 'Cotonou')
        ->assertJsonPath('data.destination', 'Parakou')
        ->assertJsonPath('data.weight_grams', 5001);
    $publicData = $tracking->json('data');
    expect(array_keys($publicData))->toBe([
        'reference', 'status', 'origin', 'destination', 'weight_grams',
        'withdraw_by', 'eta', 'last_event_at', 'timeline',
    ])->and($publicData)->not->toHaveKey('sender_name')
        ->not->toHaveKey('recipient_name')
        ->not->toHaveKey('sender_phone')
        ->not->toHaveKey('recipient_phone')
        ->not->toHaveKey('contents')
        ->not->toHaveKey('handoff_code')
        ->not->toHaveKey('share_hash');
    $this->getJson($web.'/documents?kind=label')->assertOk()->assertJsonPath(
        'data.qr_payload',
        route('api.v1.parcels.tracking', ['reference' => $parcel->reference]),
    );
    $this->getJson($web.'/documents?kind=receipt')->assertOk()->assertJsonPath('data.fiscal_status', 'pending');
    $this->postJson($web.'/ready')->assertOk()->assertJsonPath('data.status', 'ready');

    $capacity = '/parcels/data/departures/'.$departure->uuid.'/capacity';
    $this->actingAs($ops, 'web')->putJson($capacity, ['capacity_grams' => 5000])->assertOk();
    $this->postJson($web.'/assign', ['departure_uuid' => $departure->uuid])->assertConflict();
    $this->putJson($capacity, ['capacity_grams' => 5001])->assertOk();
    $this->postJson($web.'/assign', ['departure_uuid' => $departure->uuid])->assertOk();

    $terminal = TerminalP501::factory()->active()->create();
    $departedAt = now()->addSeconds(5);
    $observedAt = now();
    $this->travel(10)->seconds();
    $departure->update(['status' => DepartureStatus::Departed, 'departed_at' => $departedAt]);
    expect($parcel->refresh()->status)->toBe('assigned');

    $batch = app(ProcessSyncBatchAction::class);
    $scan = [
        'operation_uuid' => (string) Str::uuid(), 'operation_type' => 'parcel.load', 'local_recorded_at' => $observedAt->toIso8601String(),
        'payload' => ['departure_uuid' => $departure->uuid, 'reference' => $parcel->reference, 'stop_uuid' => $originStop->uuid],
    ];
    $revoked = TerminalP501::factory()->lost()->create();
    expect($batch->execute($revoked, $conductor, (string) Str::uuid(), [
        [...$scan, 'operation_uuid' => (string) Str::uuid()],
    ])->summary['rejected'])->toBe(1);
    expect($batch->execute($terminal, $conductor, (string) Str::uuid(), [$scan])->summary['accepted'])->toBe(1)
        ->and($parcel->refresh()->status)->toBe('in_transit');
    expect($batch->execute($terminal, $conductor, (string) Str::uuid(), [$scan])->summary['accepted'])->toBe(1);
    $unload = [
        ...$scan, 'operation_uuid' => (string) Str::uuid(), 'operation_type' => 'parcel.unload',
        'local_recorded_at' => now()->toIso8601String(),
        'payload' => [...$scan['payload'], 'stop_uuid' => $destinationStop->uuid],
    ];
    expect($batch->execute($terminal, $conductor, (string) Str::uuid(), [$unload])->summary['accepted'])->toBe(1);
    $this->actingAs($agent, 'web')->postJson($web.'/receive')->assertForbidden();
    $this->actingAs($receiver, 'web')->postJson($web.'/receive')->assertOk()->assertJsonPath('data.status', 'available');
    $this->postJson($web.'/release', ['code' => '000000'])->assertUnprocessable();
    expect($parcel->refresh()->handoff_attempts)->toBe(1);
    $message = ParcelOutbox::query()->where('kind', 'withdrawal_code')->firstOrFail()->payload['message'];
    $code = substr($message, -7, 6);
    $this->postJson($web.'/release', ['code' => $code])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    $this->postJson($web.'/release', ['code' => $code])->assertConflict();
    expect($parcel->refresh()->handoff_hash)->toBeNull()
        ->and($parcel->events()->where('kind', 'loaded')->count())->toBe(1);
    app(ParcelHandoff::class)->advanceDueParcels();
    $this->travelBack();
});

it('records a refusal and sends a late provider confirmation to reconciliation', function (): void {
    extract(parcelScenario());
    $this->actingAs($agent, 'web');
    $uuid = $this->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $charge = $parcel->charges()->firstOrFail();
    $payment = Payment::query()->create([
        'idempotency_key' => (string) Str::uuid(), 'parcel_charge_id' => $charge->id, 'method' => 'mobile_money', 'provider' => 'fake',
        'amount' => 1500, 'currency' => 'XOF', 'status' => PaymentStatus::Pending,
    ]);
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'weapon'])->assertOk()->assertJsonPath('data.status', 'refused');
    $payment = app(ParcelPayments::class)->confirm($payment, PaymentStatus::Confirmed, 'late', []);
    expect($payment->failure_code)->toBe('parcel_reconciliation_required')
        ->and($charge->refresh()->settled_payment_id)->toBeNull()
        ->and($parcel->refresh()->reference)->toMatch('/^KC-\d{8}-\d{5}$/')
        ->and($parcel->events()->where('kind', 'payment_reconciliation_required')->count())->toBe(1);
});

it('releases unloaded cargo on departure cancellation and opens a single overdue return', function (): void {
    extract(parcelScenario());
    $this->actingAs($agent, 'web');
    $uuid = $this->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson($web.'/pay', ['method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid()])->assertSuccessful();
    $this->postJson($web.'/ready')->assertOk();
    $this->actingAs($ops, 'web')->putJson('/parcels/data/departures/'.$departure->uuid.'/capacity', ['capacity_grams' => 5000])->assertOk();
    $this->postJson($web.'/assign', ['departure_uuid' => $departure->uuid])->assertOk();
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $departure->update(['status' => DepartureStatus::Cancelled]);
    expect($parcel->refresh()->status)->toBe('ready')
        ->and($parcel->assignments()->firstOrFail()->released_at)->not->toBeNull()
        ->and($parcel->custody_hub_id)->toBe($corridor->origin_hub_id);

    $parcel->update([
        'status' => 'available', 'custody_hub_id' => $corridor->destination_hub_id,
        'available_at' => now()->subDays(30), 'return_due_at' => now()->subSecond(),
    ]);
    $handoff = app(ParcelHandoff::class);
    $handoff->advanceDueParcels();
    $handoff->advanceDueParcels();
    expect($parcel->refresh()->status)->toBe('return_pending')
        ->and($parcel->events()->where('kind', 'return_pending')->count())->toBe(1);
    $this->actingAs($receiver, 'web')->postJson($web.'/release', ['code' => '000000'])->assertConflict();

    $reverse = Departure::query()->create([
        'line_id' => $departure->line_id, 'bus_id' => $departure->bus_id, 'conductor_id' => $conductor->id,
        'departure_date' => now()->toDateString(), 'departure_time' => '14:00', 'status' => DepartureStatus::Boarding,
        'stop_times' => [['stop_uuid' => $destinationStop->uuid], ['stop_uuid' => $originStop->uuid]],
    ]);
    $this->actingAs($ops, 'web')->putJson('/parcels/data/departures/'.$reverse->uuid.'/capacity', ['capacity_grams' => 5000])->assertOk();
    $this->postJson($web.'/assign', ['departure_uuid' => $reverse->uuid])->assertOk();
    $terminal = TerminalP501::factory()->active()->create();
    $transport = app(ParcelTransport::class);
    $transport->scan($reverse, $conductor, $terminal, $parcel->reference, $destinationStop->uuid, true, now(), (string) Str::uuid());
    $reverse->update(['status' => DepartureStatus::Departed, 'departed_at' => now()]);
    $transport->scan($reverse, $conductor, $terminal, $parcel->reference, $originStop->uuid, false, now(), (string) Str::uuid());
    $this->actingAs($agent, 'web')->postJson($web.'/receive')->assertOk()->assertJsonPath('data.status', 'returned');
    $this->postJson($web.'/return-charge')->assertOk()->assertJsonPath('data.quote.total', 600);
    $this->postJson($web.'/return-charge')->assertOk();
    expect($parcel->charges()->where('return_leg', true)->count())->toBe(1);
    $message = ParcelOutbox::query()->where('kind', 'return_code')->firstOrFail()->payload['message'];
    $code = substr($message, -7, 6);
    $this->postJson($web.'/release', ['code' => $code])->assertConflict();
    $this->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 600, 'cash_received_amount' => 600, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $this->postJson($web.'/release', ['code' => $code])->assertOk()->assertJsonPath('data.status', 'returned');
    expect($parcel->refresh()->return_handed_over_at)->not->toBeNull();
});

it('separates finance approval from a completed restitution and keeps the HT export balanced', function (): void {
    extract(parcelScenario());
    $this->actingAs($agent, 'web');
    $uuid = $this->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $paymentUuid = $this->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful()->json('data.uuid');
    $request = ['payment_uuid' => $paymentUuid, 'amount' => 1500, 'reason' => 'Annulation avant validation', 'idempotency_key' => (string) Str::uuid()];
    $this->postJson($web.'/refunds', $request)->assertForbidden();
    $finance = User::factory()->create()->assignRole('daf');
    $refund = $this->actingAs($finance, 'web')->postJson($web.'/refunds', $request)
        ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.uuid');
    $payment = Payment::query()->where('uuid', $paymentUuid)->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Confirmed);
    $this->postJson($web.'/refunds/'.$refund.'/complete', ['proof' => 'RESTIT-0001', 'cash_ledger_uuid' => $ledger->uuid])
        ->assertOk()->assertJsonPath('data.status', 'completed');
    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
    $period = RevenuePeriod::fromFilters([]);
    expect(app(ParcelRevenue::class)->totals($period))
        ->toBe(['net' => 0, 'gross' => 0, 'unassigned' => 0, 'entryCount' => 2]);
});

it('verifies the provider amount, currency and correlation before settling an online parcel payment', function (): void {
    extract(parcelScenario());
    $uuid = $this->actingAs($agent, 'web')->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $this->postJson('/parcels/data/'.$uuid.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $charge = $parcel->charges()->firstOrFail();
    $payment = Payment::query()->create([
        'idempotency_key' => (string) Str::uuid(), 'parcel_charge_id' => $charge->id, 'method' => 'mobile_money',
        'provider' => 'kkiapay', 'provider_amount' => 1500, 'provider_currency' => 'XOF',
        'amount' => 1500, 'currency' => 'XOF', 'status' => PaymentStatus::Pending,
    ]);
    $service = app(ParcelPayments::class);
    $response = fn (array $values) => new ProviderResponse(PaymentStatus::Confirmed, 'provider-receipt', $values);
    $verified = ['partnerId' => $payment->uuid, 'amount' => 1500, 'currency' => 'XOF'];
    foreach ([['amount' => 1499], ['currency' => 'EUR'], ['partnerId' => (string) Str::uuid()]] as $mismatch) {
        expect($service->confirmResponse($payment, $response(array_replace($verified, $mismatch)))->status)->toBe(PaymentStatus::Pending);
    }
    expect($parcel->refresh()->status)->toBe('weighed');
    $service->confirmResponse($payment, $response($verified));
    $service->confirmResponse($payment, $response($verified));
    expect($parcel->refresh()->status)->toBe('registered')
        ->and($charge->refresh()->settled_payment_id)->toBe($payment->id)
        ->and(FiscalInvoice::query()->count())->toBe(1)
        ->and(CashMovement::query()->count())->toBe(0);
});

it('debits points only once when the sender pays the measured quote from Wallet', function (): void {
    extract(parcelScenario());
    Sanctum::actingAs($sender);
    $uuid = $this->postJson('/api/v1/parcels', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $this->actingAs($agent, 'web')->postJson('/parcels/data/'.$uuid.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $wallet = app(FindOrCreateWalletAction::class)->handle($sender);
    $wallet->update(['points_balance' => 1500, 'points_expires_at' => now()->addMonth()]);
    Sanctum::actingAs($sender);
    $api = '/api/v1/parcels/'.$uuid.'/payments';
    $body = ['method' => 'wallet', 'amount' => 1500, 'points_amount' => 1500, 'idempotency_key' => (string) Str::uuid()];
    $paymentUuid = $this->postJson($api, $body)->assertSuccessful()->assertJsonPath('data.next_action', 'show_parcel')->json('data.uuid');
    $this->getJson($api.'/'.$paymentUuid)->assertOk()->assertJsonPath('data.wallet_funding.points_amount', 1500);
    $this->postJson($api, $body)->assertOk();
    expect($wallet->refresh()->points_balance)->toBe(0)->and($wallet->transactions()->count())->toBe(1);
});

it('only discloses parcel details to a recipient with a consumed verification for their current phone', function (): void {
    extract(parcelScenario());
    $uuid = $this->actingAs($agent, 'web')->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $this->postJson('/parcels/data/'.$uuid.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson('/parcels/data/'.$uuid.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $recipient = User::factory()->create(['phone' => '+2290191000001'])->assignRole('passenger');
    Sanctum::actingAs($recipient);
    $this->getJson('/api/v1/parcels/'.$uuid)->assertNotFound();
    $proof = pendingOtpChallenge($recipient->phone, OtpPurpose::LoginNewDevice, '123456', user: $recipient);
    $proof->update(['verified_at' => now()]);
    $this->getJson('/api/v1/parcels/'.$uuid)->assertNotFound();
    $proof->update(['consumed_at' => now()]);
    $this->getJson('/api/v1/parcels/'.$uuid)->assertOk()->assertJsonPath('data.recipient_name', 'Destinataire privé');
    $recipient->update(['phone' => '+2290191000002']);
    $this->getJson('/api/v1/parcels/'.$uuid)->assertNotFound();
});

it('resumes a submitted fiscal invoice after an outage and prints only the certified receipt', function (): void {
    extract(parcelScenario());
    $uuid = $this->actingAs($agent, 'web')->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $settings = app(IntegrationsSettings::class);
    $settings->emecfToken = 'sandbox-test';
    $settings->emecfDefaultIfu = '1234567890123';
    $external = EmecfInvoice::query()->create([
        'uid' => (string) Str::uuid(), 'ifu' => $settings->emecfDefaultIfu, 'type' => 'FV',
        'operator_name' => 'KORA COLIS', 'status' => 'pending',
    ]);
    $gateway = Mockery::mock(EmecfService::class);
    $gateway->shouldReceive('submitInvoice')->once()->andReturn([
        'success' => true, 'data' => ['uid' => $external->uid], 'invoice_id' => $external->id,
    ]);
    $gateway->shouldReceive('finalizeInvoice')->twice()->with($external->uid, 'confirm')->andReturn(['success' => false]);
    $gateway->shouldReceive('getInvoiceStatus')->twice()->with($external->uid)->andReturn(
        ['success' => false],
        ['success' => true, 'data' => ['codeMECeFDGI' => 'TEST12345678901234567890', 'qrCode' => 'DGI-CERTIFIED-QR']],
    );
    app()->instance(EmecfService::class, $gateway);
    $invoice = FiscalInvoice::query()->firstOrFail();
    $job = new FiscalizeParcelInvoiceJob($invoice->id);
    $attempts = app(FiscalSyncAttemptRecorder::class);
    $snapshots = app(EmecfInvoiceSnapshotService::class);
    expect(fn () => $job->handle($settings, $attempts, $snapshots))->toThrow(RuntimeException::class);
    $provisional = $this->getJson($web.'/documents?kind=receipt')->assertOk()->assertJsonPath('data.fiscal_status', 'failed')
        ->assertJsonMissingPath('data.fiscal_code');
    expect(base64_decode((string) $provisional->json('data.esc_pos_base64'), true))->toContain('RECU PROVISOIRE');
    $job->handle($settings, $attempts, $snapshots);
    $this->getJson($web.'/documents?kind=receipt')->assertOk()
        ->assertJsonPath('data.fiscal_status', 'certified')->assertJsonPath('data.fiscal_qr_payload', 'DGI-CERTIFIED-QR');
    expect($invoice->refresh()->mecef_uid)->toBe($external->uid);
});

it('requires reception before handoff and keeps the pickup code secret, single-use and reissuable', function (): void {
    extract(parcelScenario());
    $this->actingAs($agent, 'web');
    $uuid = $this->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $parcel->update(['status' => 'arrived', 'custody_hub_id' => $corridor->destination_hub_id, 'available_at' => null, 'return_due_at' => null]);
    $parcel->record('arrived', $conductor, ParcelHub::query()->findOrFail($corridor->destination_hub_id));

    $this->actingAs($receiver, 'web');
    $this->postJson($web.'/release', ['code' => '000000'])->assertConflict();
    $this->postJson($web.'/receive')->assertOk()->assertJsonPath('data.status', 'available');
    $this->getJson('/parcels/data/indicators')->assertOk()
        ->assertJsonPath('meta.available', 1)->assertJsonPath('meta.total', 1);

    $code = substr(ParcelOutbox::query()->where('kind', 'withdrawal_code')->firstOrFail()->payload['message'], -7, 6);
    $parcel->refresh();
    expect($parcel->handoff_hash)->not->toBeNull()
        ->and($parcel->handoff_hash)->not->toBe($code)
        ->and(DB::table('parcel_outboxes')->where('kind', 'withdrawal_code')->value('payload'))->not->toContain($code)
        ->and($parcel->events()->where('kind', 'available')->where('details', 'like', '%'.$code.'%')->count())->toBe(0);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($web.'/release', ['code' => '000000'])->assertUnprocessable();
    }
    $this->postJson($web.'/release', ['code' => '000000'])->assertStatus(429);
    expect($parcel->refresh()->handoff_attempts)->toBe(5);

    $this->actingAs($ops, 'web')->postJson($web.'/reissue-code')->assertOk();
    $newCode = substr(ParcelOutbox::query()->where('kind', 'withdrawal_code')->whereNull('completed_at')->firstOrFail()->payload['message'], -7, 6);
    expect($newCode)->not->toBe($code)->and($parcel->refresh()->handoff_attempts)->toBe(0);

    $this->actingAs($receiver, 'web');
    $this->postJson($web.'/release', ['code' => $newCode])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    $this->postJson($web.'/release', ['code' => $newCode])->assertConflict();
    expect($parcel->refresh()->handoff_hash)->toBeNull();

    $this->withoutVite();
    $proof = $this->get('/parcels/handoff/'.$uuid.'/print')->assertOk();
    $proof->assertInertia(fn (Assert $page) => $page
        ->component('parcel-handoff-proof')
        ->where('proof.reference', $parcel->reference)
        ->where('proof.outcome', 'withdrawn')
        ->where('proof.verification.method', 'code'));
    $proof->assertDontSee($newCode);

    $this->getJson($web.'/documents?kind=handoff_proof')->assertOk()
        ->assertJsonPath('data.kind', 'handoff_proof')->assertJsonMissingPath('data.handoff_code');
    $this->actingAs($agent, 'web')->getJson($web.'/documents?kind=handoff_proof')->assertForbidden();
});

it('allows an audited identity override only with the override permission', function (): void {
    extract(parcelScenario());
    $this->actingAs($agent, 'web');
    $uuid = $this->postJson('/parcels/data', parcelIntakePayload($corridor))->assertSuccessful()->json('data.uuid');
    $web = '/parcels/data/'.$uuid;
    $this->postJson($web.'/inspect', ['weight_grams' => 5000, 'category' => 'allowed'])->assertOk();
    $this->postJson($web.'/pay', [
        'method' => 'cash', 'amount' => 1500, 'cash_received_amount' => 1500, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful();
    $parcel = Parcel::query()->where('uuid', $uuid)->firstOrFail();
    $parcel->update(['status' => 'arrived', 'custody_hub_id' => $corridor->destination_hub_id]);
    $this->actingAs($receiver, 'web')->postJson($web.'/receive')->assertOk();
    $this->postJson($web.'/release', [
        'identity_type' => 'national_id', 'reason' => 'Code non reçu par la destinataire',
    ])->assertForbidden();

    $admin = User::factory()->create()->assignRole('system_admin');
    $this->actingAs($admin, 'web')->postJson($web.'/release', [
        'identity_type' => 'national_id', 'reason' => 'Code non reçu par la destinataire',
    ])->assertOk()->assertJsonPath('data.status', 'withdrawn')
        ->assertJsonPath('data.handoff.method', 'identity');
    $event = $parcel->events()->where('kind', 'withdrawn')->firstOrFail();
    expect($event->details)->toMatchArray([
        'identity_type' => 'national_id', 'reason' => 'Code non reçu par la destinataire',
    ])->and($event->details)->not->toHaveKey('identity_number')
        ->and($parcel->refresh()->handoff_hash)->toBeNull();
});
