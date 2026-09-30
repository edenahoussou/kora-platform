<?php

use App\Jobs\RunFiscalTestInvoice;
use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalTestInvoiceRun;
use App\Modules\Fiscalization\Services\DgiFiscalTestInvoiceGateway;
use App\Modules\Fiscalization\Services\FiscalTestCaseCatalog;
use App\Modules\Fiscalization\Services\FiscalTestInvoiceGateway;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Enums\InvoiceStatus;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function fiscalTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    return $admin;
}

function fiscalTestSettings(bool $testMode = true): IntegrationsSettings
{
    $settings = new IntegrationsSettings;
    $settings->emecfTestMode = $testMode;
    $settings->emecfToken = 'e-mecf-test-secret';
    $settings->emecfDefaultIfu = '3202673225149';
    $settings->emecfTestInvoiceUrl = 'https://developper.impots.bj/sygmef-emcf/api/';

    app()->instance(IntegrationsSettings::class, $settings);

    return $settings;
}

it('shows only the ten DGI cases to system administrators', function (): void {
    $this->actingAs(fiscalTestAdmin())
        ->get(route('fiscal-test-invoices.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('fiscal-test-invoices')
            ->has('cases', 10)
            ->where('cases.0.number', 1)
            ->where('cases.9.number', 10)
            ->missing('cases.10')
            ->missing('settings.emecfToken'));
});

it('denies the campaign page to users who are not system administrators', function (): void {
    $user = User::factory()->create();
    $user->assignRole('daf');

    $this->actingAs($user)
        ->get(route('fiscal-test-invoices.index'))
        ->assertForbidden();
});

it('creates an isolated test run with the fixed test scenario payload', function (): void {
    fiscalTestSettings();
    Queue::fake([RunFiscalTestInvoice::class]);

    $this->actingAs(fiscalTestAdmin())
        ->post(route('fiscal-test-invoices.store'), ['caseNumber' => 4])
        ->assertRedirect();

    $run = FiscalTestInvoiceRun::query()->sole();

    expect($run->request_snapshot['type'])->toBe('FV')
        ->and($run->request_snapshot['items'][0]['taxGroup'])->toBe('A')
        ->and($run->request_snapshot['items'][0]['quantity'])->toBe(2.5)
        ->and($run->request_snapshot['items'][1]['taxGroup'])->toBe('B')
        ->and($run->request_snapshot['items'][1]['quantity'])->toBe(3.25)
        ->and($run->request_snapshot)->not->toHaveKey('payment');

    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('payments', 0);
    Queue::assertPushed(RunFiscalTestInvoice::class);
});

it('requires the test mode and a client IFU before a scenario can be emitted', function (): void {
    fiscalTestSettings(testMode: false);

    $this->actingAs(fiscalTestAdmin())
        ->post(route('fiscal-test-invoices.store'), ['caseNumber' => 1])
        ->assertConflict();

    fiscalTestSettings();

    $this->actingAs(fiscalTestAdmin())
        ->post(route('fiscal-test-invoices.store'), ['caseNumber' => 5])
        ->assertSessionHasErrors('clientIfu');
});

it('requires a certified test five invoice before allowing test six', function (): void {
    fiscalTestSettings();

    $this->actingAs(fiscalTestAdmin())
        ->post(route('fiscal-test-invoices.store'), ['caseNumber' => 6])
        ->assertUnprocessable();

    $this->assertDatabaseCount('fiscal_test_invoice_runs', 0);
});

it('maps AIB and specific tax in the DGI examples', function (): void {
    $catalog = app(FiscalTestCaseCatalog::class);
    $settings = fiscalTestSettings();
    $operator = fiscalTestAdmin();

    $caseSeven = $catalog->payload(7, $settings, $operator, '1234567890123');
    $caseEight = $catalog->payload(8, $settings, $operator, null);
    $caseNine = $catalog->payload(9, $settings, $operator, '1234567890123');
    $caseTen = $catalog->payload(10, $settings, $operator, null);

    expect($caseSeven['items'][0]['taxSpecific'])->toBe(1000)
        ->and($caseSeven['client']['ifu'])->toBe('1234567890123')
        ->and($caseEight['aib'])->toBe('B')
        ->and($caseNine['aib'])->toBe('A')
        ->and($caseNine['client']['name'])->toBe('Client de démonstration KORA')
        ->and($caseTen['aib'])->toBe('B')
        ->and($caseTen['items'][1]['taxSpecific'])->toBe(1000);
});

it('sends only to the official test API with TLS verified and a server-side token', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/sygmef-emcf/api/invoice' => Http::response(['uid' => 'test-uid']),
    ]);

    $gateway = new DgiFiscalTestInvoiceGateway(fiscalTestSettings());
    $gateway->submit(['ifu' => '3202673225149', 'type' => 'FV', 'items' => []]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://developper.impots.bj/sygmef-emcf/api/invoice'
        && $request->hasHeader('Authorization', 'Bearer e-mecf-test-secret'));
});

it('refuses the provider call when test mode is disabled', function (): void {
    Http::preventStrayRequests();
    $gateway = new DgiFiscalTestInvoiceGateway(fiscalTestSettings(testMode: false));

    expect(fn () => $gateway->submit([]))
        ->toThrow(RuntimeException::class, 'uniquement en mode e‑MECeF TEST');
});

it('stores the certified e-MECeF result without creating BUS sale records', function (): void {
    $settings = fiscalTestSettings();
    $admin = fiscalTestAdmin();
    $run = FiscalTestInvoiceRun::query()->create([
        'case_number' => 1,
        'requested_by_user_id' => $admin->getKey(),
        'status' => FiscalInvoiceStatus::Pending->value,
        'request_snapshot' => app(FiscalTestCaseCatalog::class)->payload(1, $settings, $admin, null),
    ]);
    $gateway = new class implements FiscalTestInvoiceGateway
    {
        public function submit(array $payload): array
        {
            return ['uid' => 'dgi-test-uid-1', 'total' => 10000, 'ta' => 10000];
        }

        public function finalize(string $uid): array
        {
            return [
                'codeMECeFDGI' => 'ABCDEFGHIJKLMN1234567890',
                'qrCode' => 'https://developper.impots.bj/sygmef-test/verification?uid=dgi-test-uid-1',
                'dateTime' => '2026-09-30T12:00:00',
                'counters' => '00000001/00000001',
                'nim' => 'TS01019552',
            ];
        }
    };

    (new RunFiscalTestInvoice($run->getKey()))->handle($gateway, $settings);

    $run->refresh()->load('emecfInvoice.items');
    expect($run->status)->toBe(FiscalInvoiceStatus::Certified->value)
        ->and($run->emecfInvoice->status)->toBe(InvoiceStatus::CONFIRMED)
        ->and($run->emecfInvoice->qr_code)->toContain('uid=dgi-test-uid-1')
        ->and($run->emecfInvoice->items)->toHaveCount(1)
        ->and($run->emecfInvoice->items->first()->tax_group->value)->toBe('A');

    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('fiscal_invoices', 0);
});

it('builds test six as a full negative-quantity credit of the certified test five invoice', function (): void {
    $settings = fiscalTestSettings();
    $admin = fiscalTestAdmin();
    $originalInvoice = EmecfInvoice::query()->create([
        'uid' => 'original-test-uid',
        'ifu' => $settings->emecfDefaultIfu,
        'type' => 'FV',
        'operator_name' => $admin->name,
        'client_ifu' => '1234567890123',
        'client_name' => 'Client de démonstration KORA',
        'status' => InvoiceStatus::CONFIRMED,
        'code_mec_ef_dgi' => 'ABCDEFGHIJKLMN1234567890',
        'total' => 59000,
    ]);
    $originalInvoice->items()->createMany([
        ['name' => 'Article exonéré - Test DGI', 'price' => 10000, 'quantity' => 2, 'tax_group' => 'A'],
        ['name' => 'Article taxable - Test DGI', 'price' => 10000, 'quantity' => 3, 'tax_group' => 'B'],
    ]);
    $sourceRun = FiscalTestInvoiceRun::query()->create([
        'case_number' => 5,
        'requested_by_user_id' => $admin->getKey(),
        'emecf_invoice_id' => $originalInvoice->getKey(),
        'status' => FiscalInvoiceStatus::Certified->value,
        'request_snapshot' => [],
    ]);
    $creditRun = FiscalTestInvoiceRun::query()->create([
        'case_number' => 6,
        'requested_by_user_id' => $admin->getKey(),
        'source_run_id' => $sourceRun->getKey(),
        'status' => FiscalInvoiceStatus::Pending->value,
        'request_snapshot' => [],
    ]);
    $observedPayload = null;
    $gateway = new class($observedPayload) implements FiscalTestInvoiceGateway
    {
        public function __construct(public mixed &$observedPayload) {}

        public function submit(array $payload): array
        {
            $this->observedPayload = $payload;

            return ['uid' => 'credit-test-uid'];
        }

        public function finalize(string $uid): array
        {
            return ['codeMECeFDGI' => 'ZYXWVUTSRQPONM1234567890', 'qrCode' => 'dgi-qr'];
        }
    };

    (new RunFiscalTestInvoice($creditRun->getKey()))->handle($gateway, $settings);

    expect($observedPayload['type'])->toBe('FA')
        ->and($observedPayload['reference'])->toBe('ABCDEFGHIJKLMN1234567890')
        ->and($observedPayload['client']['ifu'])->toBe('1234567890123')
        ->and($observedPayload['items'][0]['quantity'])->toBe(-2.0)
        ->and($observedPayload['items'][1]['quantity'])->toBe(-3.0)
        ->and($creditRun->fresh()->status)->toBe(FiscalInvoiceStatus::Certified->value);
});
