<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\KKiaPayAdapter;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Queue::fake([FiscalizeParcelInvoiceJob::class]);
});

/** @return array<string, mixed> */
function paymentReceiptFixture(bool $partnerPoint = false): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = $partnerPoint
        ? Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ'])
        : Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create([
        'agency_id' => $originAgency->id,
        'kora_point_id' => $partnerPoint ? KoraPoint::factory()->create(['agency_id' => $originAgency->id])->id : null,
        'kind' => $partnerPoint ? ParcelHub::KIND_PARTNER_POINT : ParcelHub::KIND_AGENCY,
        'stop_id' => $originStop->id,
        'enabled' => true,
        'supports_weighing' => true,
    ]);
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
    $ops = User::factory()->create()->assignRole('operations_director');
    $corridor->tariffs()->create([
        'base_amount' => 1500,
        'return_amount' => 600,
        'created_by' => $ops->id,
    ]);
    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => Bus::query()->create([
            'registration_number' => 'PAY-'.Str::random(8), 'brand' => 'KORA', 'model' => 'Coach',
            'seat_count' => 50, 'status' => 'active',
        ])->id,
        'conductor_id' => User::factory()->create()->assignRole('conductor')->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [['stop_uuid' => $originStop->uuid], ['stop_uuid' => $destinationStop->uuid]],
    ]);
    TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $parcel = app(ParcelIntake::class)->createForDeposit($origin, $destination, $agent, [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Payment Sender',
        'sender_phone' => '+2290191000010',
        'recipient_name' => 'Payment Recipient',
        'recipient_phone' => '+2290191000011',
        'contents' => 'Documents',
        'weight_grams' => 5000,
    ], true);
    test()->actingAs($agent, 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/inspect", ['weight_grams' => 5000, 'category' => 'allowed'])
        ->assertOk();

    $session = CashSession::factory()->create([
        'agent_id' => $agent->id,
        'agency_id' => $originAgency->id,
        'business_date' => now()->toDateString(),
    ]);
    $cash = CashLedger::factory()->create(['cash_session_id' => $session->id]);
    $account = AgencyPaymentAccount::query()->create([
        'agency_id' => $originAgency->id,
        'network' => 'mtn_momo',
        'label' => 'MTN MoMo guichet',
        'account_identifier' => 'PAYMENT-RECEIPT-001',
        'status' => 'active',
    ]);
    $mobile = CashLedger::factory()->create([
        'cash_session_id' => $session->id,
        'ledger_key' => $account->uuid,
        'kind' => 'mobile_money',
        'agency_payment_account_id' => $account->id,
    ]);

    return compact('originAgency', 'destinationAgency', 'origin', 'destination', 'corridor', 'agent', 'ops', 'parcel', 'session', 'cash', 'mobile', 'account', 'line');
}

/** @param array<string, mixed> $fixture */
function payCash($test, array $fixture, ?string $key = null, ?int $amount = null, ?User $actor = null): TestResponse
{
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    return $test->actingAs($actor ?? $fixture['agent'], 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $amount ?? $charge->total,
            'cash_received_amount' => $amount ?? $charge->total,
        ]);
}

test('a FedaPay parcel webhook verifies with the authenticated transaction id missing from the payment row', function (): void {
    $fixture = paymentReceiptFixture();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $payment = Payment::query()->create([
        'parcel_charge_id' => $charge->id,
        'idempotency_key' => (string) Str::uuid(),
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'fedapay',
        'provider_reference' => 'FEDA-REF-001',
        'provider_amount' => $charge->total,
        'provider_currency' => 'XOF',
        'amount' => $charge->total,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    $provider = new WebhookCorrelationProvider(
        providerName: 'fedapay',
        expectedTransactionId: 'TX-FEDA-NEW',
        webhookPayload: [
            'id' => 'TX-FEDA-NEW',
            'status' => 'approved',
            'custom_metadata' => ['kora_payment_uuid' => $payment->uuid],
        ],
    );
    $this->app->instance(FedaPayAdapter::class, $provider);

    $this->postJson('/api/v1/payments/webhook/fedapay', $provider->webhookPayload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    expect($provider->seenTransactionId)->toBe('TX-FEDA-NEW')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->provider_transaction_id)->toBe('TX-FEDA-NEW');
});

test('a KKiaPay parcel retry uses the new authenticated id instead of the stale stored one', function (): void {
    $fixture = paymentReceiptFixture();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $payment = Payment::query()->create([
        'parcel_charge_id' => $charge->id,
        'idempotency_key' => (string) Str::uuid(),
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'kkiapay',
        'provider_reference' => 'TX-KK-OLD',
        'provider_transaction_id' => 'TX-KK-OLD',
        'provider_amount' => $charge->total,
        'provider_currency' => 'XOF',
        'amount' => $charge->total,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    $provider = new WebhookCorrelationProvider(
        providerName: 'kkiapay',
        expectedTransactionId: 'TX-KK-NEW',
        webhookPayload: [
            'transactionId' => 'TX-KK-NEW',
            'partnerId' => $payment->uuid,
            'status' => 'SUCCESS',
        ],
    );
    $this->app->instance(KKiaPayAdapter::class, $provider);

    $this->postJson('/api/v1/payments/webhook/kkiapay', $provider->webhookPayload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    expect($provider->seenTransactionId)->toBe('TX-KK-NEW')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->provider_transaction_id)->toBe('TX-KK-NEW');
});

test('a cash deposit settles one charge, one cash movement and one invoice', function (): void {
    $fixture = paymentReceiptFixture();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $this->actingAs($fixture['agent'], 'web')
        ->getJson("/parcels/data/{$fixture['parcel']->uuid}/payment-options")
        ->assertOk()
        ->assertJsonPath('data.cash_session.uuid', $fixture['session']->uuid)
        ->assertJsonPath('data.agency.uuid', $fixture['originAgency']->uuid)
        ->assertJsonPath('data.methods.0.available', true)
        ->assertJsonPath('data.methods.1.accounts.0.uuid', $fixture['account']->uuid);

    payCash($this, $fixture)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.method', 'cash')
        ->assertJsonPath('data.amount', $charge->total)
        ->assertJsonPath('data.currency', 'XOF');

    $payment = Payment::query()->sole();
    $parcel = $fixture['parcel']->fresh();

    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($charge->fresh()->settled_payment_id)->toBe($payment->id)
        ->and($parcel->status)->toBe('registered')
        ->and($parcel->reference)->not->toBeNull()
        ->and(CashMovement::query()->where('source_type', Payment::class)->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1);

    $receipt = $this->getJson("/parcels/data/{$parcel->uuid}/receipt")
        ->assertOk()
        ->assertJsonPath('data.receipt_status', 'provisional')
        ->assertJsonPath('data.fiscal.certified', false)
        ->assertJsonPath('data.fiscal.code', null)
        ->assertJsonPath('data.charge.total', $charge->total)
        ->assertJsonPath('data.payment.method', 'cash')
        ->assertJsonPath('data.parties.sender_name', 'Payment Sender');

    expect($receipt->json('data.qr_payload'))->toBe(route('api.v1.parcels.tracking', ['reference' => $parcel->reference]));

    $document = $this->getJson("/parcels/data/{$parcel->uuid}/documents?kind=receipt")->assertOk();
    expect($document->json('data.fiscal_status'))->toBe('pending')
        ->and(base64_decode((string) $document->json('data.esc_pos_base64'), true))->toContain('RECU PROVISOIRE');
});

test('the same idempotency key returns the same payment without a second charge or movement', function (): void {
    $fixture = paymentReceiptFixture();
    $key = (string) Str::uuid();

    $first = payCash($this, $fixture, $key)->assertSuccessful();
    $second = payCash($this, $fixture, $key)->assertSuccessful();

    expect($second->json('data.uuid'))->toBe($first->json('data.uuid'))
        ->and(Payment::query()->count())->toBe(1)
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1);
});

test('a settled charge cannot be collected twice with another key', function (): void {
    $fixture = paymentReceiptFixture();
    payCash($this, $fixture)->assertSuccessful();

    payCash($this, $fixture)->assertConflict();
    expect(Payment::query()->count())->toBe(1);
});

test('a configured electronic account settles through its own cash ledger', function (): void {
    $fixture = paymentReceiptFixture();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $payment = $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'mobile_money',
            'amount' => $charge->total,
            'agency_payment_account_uuid' => $fixture['account']->uuid,
            'provider_reference' => 'MOMO-OPS-001',
            'mobile_money_network' => 'mtn_momo',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.method', 'mobile_money');

    $record = Payment::query()->where('uuid', $payment->json('data.uuid'))->sole();

    expect($record->agency_payment_account_id)->toBe($fixture['account']->id)
        ->and($record->cash_ledger_id)->toBe($fixture['mobile']->id)
        ->and(CashMovement::query()->where('source_type', Payment::class)->where('type', 'parcel_sale_mobile_money')->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1);
});

test('a mobile money collection requires an active account and provider reference', function (): void {
    $fixture = paymentReceiptFixture();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'mobile_money',
            'amount' => $charge->total,
            'mobile_money_network' => 'mtn_momo',
        ])
        ->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

test('a closed or missing cash session blocks the collection', function (): void {
    $fixture = paymentReceiptFixture();
    $fixture['session']->update(['status' => 'closed']);

    payCash($this, $fixture)->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

test('the cash session must belong to the parcel deposit agency', function (): void {
    $fixture = paymentReceiptFixture();
    $fixture['session']->update(['status' => 'closed']);
    $otherAgency = Agency::factory()->create(['country' => 'BJ']);
    $networkAgent = User::factory()->create(['agency_id' => $otherAgency->id])->assignRole('operations_director');
    CashSession::factory()->create([
        'agent_id' => $networkAgent->id,
        'agency_id' => $otherAgency->id,
        'business_date' => now()->toDateString(),
    ]);
    $this->actingAs($networkAgent, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $fixture['parcel']->charges()->whereNull('superseded_at')->sole()->total,
            'cash_received_amount' => $fixture['parcel']->charges()->whereNull('superseded_at')->sole()->total,
        ])
        ->assertConflict();

    expect(Payment::query()->count())->toBe(0);
});

test('a finance role can inspect payments, invoices, attempts and reconciliations', function (): void {
    $fixture = paymentReceiptFixture();
    payCash($this, $fixture)->assertSuccessful();
    $daf = User::factory()->create()->assignRole('daf');

    $this->actingAs($daf, 'web')
        ->getJson("/parcels/data/{$fixture['parcel']->uuid}/payments")
        ->assertOk()
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonCount(1, 'data.invoices')
        ->assertJsonPath('data.invoices.0.status', 'pending')
        ->assertJsonPath('data.payments.0.reconciliation_required', false);
});

test('fiscalization failure retries without creating a duplicate invoice', function (): void {
    $fixture = paymentReceiptFixture();
    payCash($this, $fixture)->assertSuccessful();
    $invoice = FiscalInvoice::query()->sole();
    $invoice->update(['status' => FiscalInvoiceStatus::Failed]);
    $daf = User::factory()->create()->assignRole('daf');

    $this->actingAs($daf, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/fiscalization/retry")
        ->assertOk()
        ->assertJsonPath('data.dispatched', 1);

    $this->actingAs($daf, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/fiscalization/retry")
        ->assertOk();

    expect(FiscalInvoice::query()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(FiscalInvoiceStatus::Failed)
        ->and($invoice->fresh()->syncAttempts()->count())->toBe(0);
});

test('a late confirmation becomes a reconciliation without a second settlement', function (): void {
    $fixture = paymentReceiptFixture();
    payCash($this, $fixture)->assertSuccessful();
    $first = Payment::query()->sole();
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $late = Payment::query()->create([
        'parcel_charge_id' => $charge->id,
        'idempotency_key' => (string) Str::uuid(),
        'request_fingerprint' => 'late-confirmation',
        'method' => PaymentMethod::Cash,
        'provider' => 'cash',
        'amount' => $charge->total,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
        'wallet_funding' => ['wallet_amount' => 0, 'points_amount' => 0, 'point_value_fcfa' => 1, 'points_value_fcfa' => 0, 'provider_amount_fcfa' => $charge->total],
    ]);

    app(ParcelPayments::class)->confirm($late, PaymentStatus::Confirmed, 'LATE-CASH-001', [], 'LATE-CASH-001');

    expect($late->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($late->fresh()->failure_code)->toBe('parcel_reconciliation_required')
        ->and($charge->fresh()->settled_payment_id)->toBe($first->id)
        ->and(CashMovement::query()->count())->toBe(1)
        ->and(FiscalInvoice::query()->count())->toBe(1);
});

test('a non-intake user cannot collect a parcel', function (): void {
    $fixture = paymentReceiptFixture();
    $viewer = User::factory()->create()->assignRole('general_management');

    payCash($this, $fixture, actor: $viewer)->assertForbidden();
});

test('a partner point collects cash without a cash session or treasury movement', function (): void {
    $fixture = paymentReceiptFixture(partnerPoint: true);
    $fixture['parcel']->load('corridor.originHub.agency');
    $fixture['mobile']->delete();
    $fixture['cash']->delete();
    $fixture['session']->delete();
    $pointAgent = User::factory()->create(['agency_id' => $fixture['originAgency']->id])
        ->assignRole('kora_point_agent');
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $this->actingAs($pointAgent, 'web')
        ->getJson("/parcels/data/{$fixture['parcel']->uuid}/payment-options")
        ->assertOk()
        ->assertJsonPath('data.is_partner', true)
        ->assertJsonPath('data.cash_session', null)
        ->assertJsonPath('data.methods.0.available', true)
        ->assertJsonPath('data.methods.1.available', true)
        ->assertJsonPath('data.methods.1.accounts', []);

    $payment = $this->actingAs($pointAgent, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $charge->total,
            'cash_received_amount' => $charge->total + 500,
            'payer_name' => 'Client guichet partenaire',
            'payer_phone' => '+2290191000020',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.method', 'cash');

    $record = Payment::query()->where('uuid', $payment->json('data.uuid'))->sole();

    expect($record->cash_session_id)->toBeNull()
        ->and($record->cash_ledger_id)->toBeNull()
        ->and($record->change_amount)->toBe(500)
        ->and(CashMovement::query()->where('source_type', Payment::class)->count())->toBe(0);
});

test('a partner point collects mobile money without an agency account or treasury movement', function (): void {
    $fixture = paymentReceiptFixture(partnerPoint: true);
    $fixture['mobile']->delete();
    $fixture['cash']->delete();
    $fixture['session']->delete();
    $pointAgent = User::factory()->create(['agency_id' => $fixture['originAgency']->id])
        ->assignRole('kora_point_agent');
    $charge = $fixture['parcel']->charges()->whereNull('superseded_at')->sole();

    $payment = $this->actingAs($pointAgent, 'web')
        ->postJson("/parcels/data/{$fixture['parcel']->uuid}/pay", [
            'idempotency_key' => (string) Str::uuid(),
            'method' => 'mobile_money',
            'amount' => $charge->total,
            'mobile_money_network' => 'mtn_momo',
            'provider_reference' => 'POINT-MM-'.Str::random(10),
            'payer_name' => 'Client MoMo partenaire',
            'payer_phone' => '+2290191000021',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.method', 'mobile_money');

    $record = Payment::query()->where('uuid', $payment->json('data.uuid'))->sole();

    expect($record->agency_payment_account_id)->toBeNull()
        ->and($record->cash_session_id)->toBeNull()
        ->and($record->cash_ledger_id)->toBeNull()
        ->and($record->provider)->toBe('mtn_momo')
        ->and(CashMovement::query()->where('source_type', Payment::class)->count())->toBe(0);
});

final class WebhookCorrelationProvider implements PaymentProvider
{
    public ?string $seenTransactionId = null;

    public function __construct(
        private readonly string $providerName,
        private readonly string $expectedTransactionId,
        public readonly array $webhookPayload,
    ) {}

    public function initiate(Payment $payment, array $payload = []): ProviderResponse
    {
        return new ProviderResponse(status: PaymentStatus::Pending);
    }

    public function verify(Payment $payment): ProviderResponse
    {
        $this->seenTransactionId = $payment->provider_transaction_id;

        if ($this->seenTransactionId !== $this->expectedTransactionId) {
            return new ProviderResponse(
                status: PaymentStatus::Pending,
                providerReference: $payment->provider_reference,
                payload: ['reason' => 'transaction_id_missing'],
            );
        }

        $payload = match ($this->providerName) {
            'fedapay' => [
                'custom_metadata' => ['kora_payment_uuid' => $payment->uuid],
                'currency' => ['iso' => $payment->provider_currency],
                'amount' => (string) $payment->provider_amount,
                'status' => 'approved',
            ],
            'kkiapay' => [
                'partnerId' => $payment->uuid,
                'currency' => ['iso' => $payment->provider_currency],
                'amount' => (string) $payment->provider_amount,
                'status' => 'SUCCESS',
            ],
            default => [],
        };

        return new ProviderResponse(
            status: PaymentStatus::Confirmed,
            providerReference: $this->expectedTransactionId,
            providerTransactionId: $this->expectedTransactionId,
            payload: $payload,
        );
    }

    public function verifyWebhook(Request $request): ?array
    {
        return $this->webhookPayload;
    }

    public function supports(string $method, string $provider): bool
    {
        return $provider === $this->providerName;
    }

    public function name(): string
    {
        return $this->providerName;
    }
}
