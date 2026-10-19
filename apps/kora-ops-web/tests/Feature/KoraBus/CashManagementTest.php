<?php

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Database\Seeders\KoraBusReferenceSeeder;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraCashDemoSeeder;
use Database\Seeders\KoraCounterSaleSeeder;
use Database\Seeders\KoraDemoUserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    Storage::fake('private');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('opens one cash session with every active agency account and private evidence', function (): void {
    $scenario = cashManagementScenario();

    $response = $this->actingAs($scenario['agent'])->post(route('cash.open'), [
        'ledgers' => [
            cashOpeningRow(125000),
            cashOpeningRow(300000, $scenario['accounts'][0]->uuid),
            cashOpeningRow(100000, $scenario['accounts'][1]->uuid),
        ],
    ]);

    $response->assertRedirect();
    $session = CashSession::query()->firstOrFail();
    expect($session->ledgers)->toHaveCount(3)
        ->and($session->ledgers->pluck('kind')->map(fn ($kind): string => $kind->value)->all())
        ->toContain('cash', 'mobile_money');
    expect($session->ledgers->every(fn (CashLedger $ledger): bool => $ledger->hasMedia('opening-evidence')))->toBeTrue();
    expect($session->ledgers->first()->getFirstMedia('opening-evidence')->disk)->toBe('private');

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), [
            'ledgers' => [cashOpeningRow(1)],
        ])
        ->assertSessionHasErrors('cash_session');
});

it('refuses to open on another register while a session stays open from yesterday', function (): void {
    $scenario = cashManagementScenario();
    $registerOne = CashRegister::factory()->create(['agency_id' => $scenario['agency']->id]);
    $registerTwo = CashRegister::factory()->create(['agency_id' => $scenario['agency']->id]);

    $stale = createCashManagementSession($scenario);
    $stale->cash_register_id = $registerOne->id;
    $stale->business_date = '2026-08-18';
    $stale->save();

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), [
            'cash_register_uuid' => $registerTwo->uuid,
            'ledgers' => [
                cashOpeningRow(125000),
                cashOpeningRow(300000, $scenario['accounts'][0]->uuid),
                cashOpeningRow(100000, $scenario['accounts'][1]->uuid),
            ],
        ])
        ->assertSessionHasErrors('cash_session');

    expect(CashSession::query()->count())->toBe(1);
});

it('keeps a pending reversement out of the expected balance until approval', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);
    $ledger = $session->ledgers()->where('kind', 'cash')->firstOrFail();

    $this->actingAs($scenario['agent'])->post(route('cash.movements.store'), [
        'ledger_uuid' => $ledger->uuid,
        'amount' => 25000,
        'type' => 'reversement',
        'destination_label' => 'Trésorerie KORA',
        'destination_reference' => 'REV-001',
        'description' => 'Reversement du matin',
        'evidence' => UploadedFile::fake()->image('transfer.png'),
    ])->assertRedirect();

    $movement = CashMovement::query()->firstOrFail();
    expect($movement->status->value)->toBe('pending')
        ->and($ledger->fresh()->expected_balance)->toBe(125000);

    $this->actingAs($scenario['agent'])->post(route('cash.movements.approve', $movement))->assertRedirect();

    expect($movement->fresh()->status->value)->toBe('approved')
        ->and($ledger->fresh()->expected_balance)->toBe(100000);
});

it('submits a balanced closure with evidence for every ledger', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);

    $payload = ['ledgers' => $session->ledgers->map(fn (CashLedger $ledger): array => [
        'uuid' => $ledger->uuid,
        'actual_balance' => $ledger->opening_balance,
        'closing_comment' => '',
        'evidence' => UploadedFile::fake()->image('closing.jpg'),
        'evidence_captured_at' => now()->toISOString(),
    ])->all()];

    $this->actingAs($scenario['agent'])
        ->post(route('cash.close', $session), $payload)
        ->assertRedirect();

    expect($session->fresh()->status->value)->toBe('closed')
        ->and($session->fresh()->ledgers->every(fn (CashLedger $ledger): bool => $ledger->hasMedia('closing-evidence')))->toBeTrue();
});

it('keeps the operational cash demo seeder idempotent and out of the default seed', function (): void {
    $this->seed([
        KoraBusReferenceSeeder::class,
        KoraDemoUserSeeder::class,
        KoraCounterSaleSeeder::class,
    ]);

    expect(CashSession::query()->count())->toBe(0);

    $this->seed(KoraCashDemoSeeder::class);
    $this->seed(KoraCashDemoSeeder::class);

    $session = CashSession::query()->firstOrFail();
    expect(CashSession::query()->count())->toBe(1)
        ->and($session->ledgers)->toHaveCount(4)
        ->and(CashMovement::query()->count())->toBe(2)
        ->and($session->ledgers->every(fn (CashLedger $ledger): bool => $ledger->hasMedia('opening-evidence')))->toBeTrue();
});

/** @return array{agent: User, agency: Agency, accounts: list<AgencyPaymentAccount>} */
function cashManagementScenario(): array
{
    $agency = Agency::query()->create([
        'code' => 'CASH-AGENCY',
        'name' => 'Agence Caisse',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_manager');
    $accounts = [
        AgencyPaymentAccount::query()->create(['agency_id' => $agency->id, 'network' => 'mtn_momo', 'label' => 'MTN agence', 'account_identifier' => '+22997000001', 'status' => 'active']),
        AgencyPaymentAccount::query()->create(['agency_id' => $agency->id, 'network' => 'moov_flooz', 'label' => 'Flooz agence', 'account_identifier' => '+22996000001', 'status' => 'active']),
    ];

    return compact('agent', 'agency', 'accounts');
}

it('rejects cash opening evidence whose capture timestamp is stale', function (): void {
    $scenario = cashManagementScenario();

    $row = cashOpeningRow(125000);
    $row['evidence_captured_at'] = now()->subMinutes(30)->toISOString();

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), ['ledgers' => [$row]])
        ->assertSessionHasErrors('ledgers.0.evidence_captured_at');

    expect(CashSession::query()->count())->toBe(0);
});

it('rejects cash opening evidence whose capture timestamp is in the future', function (): void {
    $scenario = cashManagementScenario();

    $row = cashOpeningRow(125000);
    $row['evidence_captured_at'] = now()->addMinutes(10)->toISOString();

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), ['ledgers' => [$row]])
        ->assertSessionHasErrors('ledgers.0.evidence_captured_at');
});

it('rejects a cash opening submitted without a capture timestamp', function (): void {
    $scenario = cashManagementScenario();

    $row = cashOpeningRow(125000);
    unset($row['evidence_captured_at']);

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), ['ledgers' => [$row]])
        ->assertSessionHasErrors('ledgers.0.evidence_captured_at');
});

it('records the client capture timestamp and live-camera source on the opening evidence media', function (): void {
    $scenario = cashManagementScenario();
    $capturedAt = now()->subMinutes(2)->toISOString();

    $row = cashOpeningRow(125000);
    $row['evidence_captured_at'] = $capturedAt;

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), [
            'ledgers' => [
                $row,
                cashOpeningRow(300000, $scenario['accounts'][0]->uuid),
                cashOpeningRow(100000, $scenario['accounts'][1]->uuid),
            ],
        ])
        ->assertRedirect();

    $ledger = CashSession::query()->firstOrFail()
        ->ledgers()->where('kind', 'cash')->firstOrFail();
    $media = $ledger->getFirstMedia('opening-evidence');

    expect($media->getCustomProperty('evidence_source'))->toBe('live_camera')
        ->and($media->getCustomProperty('captured_at_client'))->toBe($capturedAt);
});

it('rejects cash closing evidence whose capture timestamp is stale', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);

    $payload = ['ledgers' => $session->ledgers->map(fn (CashLedger $ledger): array => [
        'uuid' => $ledger->uuid,
        'actual_balance' => $ledger->opening_balance,
        'closing_comment' => '',
        'evidence' => UploadedFile::fake()->image('closing.jpg'),
        'evidence_captured_at' => now()->subHours(2)->toISOString(),
    ])->all()];

    $this->actingAs($scenario['agent'])
        ->post(route('cash.close', $session), $payload)
        ->assertSessionHasErrors('ledgers.0.evidence_captured_at');

    expect($session->fresh()->status->value)->toBe('open');
});

it('rejects a cash closing submitted without a capture timestamp', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);

    $payload = ['ledgers' => $session->ledgers->map(fn (CashLedger $ledger): array => [
        'uuid' => $ledger->uuid,
        'actual_balance' => $ledger->opening_balance,
        'closing_comment' => '',
        'evidence' => UploadedFile::fake()->image('closing.jpg'),
    ])->all()];

    $this->actingAs($scenario['agent'])
        ->post(route('cash.close', $session), $payload)
        ->assertSessionHasErrors('ledgers.0.evidence_captured_at');
});

/** @return array{kind: string, account_uuid: string|null, opening_balance: int, evidence: UploadedFile, evidence_captured_at: string} */
function cashOpeningRow(int $balance, ?string $accountUuid = null): array
{
    return [
        'kind' => $accountUuid === null ? 'cash' : 'mobile_money',
        'account_uuid' => $accountUuid,
        'opening_balance' => $balance,
        'evidence' => UploadedFile::fake()->image('opening.jpg'),
        'evidence_captured_at' => now()->toISOString(),
    ];
}

/** @param array{agent: User, agency: Agency, accounts: list<AgencyPaymentAccount>} $scenario */
function createCashManagementSession(array $scenario): CashSession
{
    $session = CashSession::query()->create([
        'agent_id' => $scenario['agent']->id,
        'agency_id' => $scenario['agency']->id,
        'business_date' => '2026-08-19',
        'opening_float' => 125000,
        'expected_balance' => 125000,
        'status' => 'open',
        'opened_at' => now(),
    ]);
    CashLedger::query()->create([
        'cash_session_id' => $session->id,
        'ledger_key' => 'cash',
        'kind' => 'cash',
        'opening_balance' => 125000,
        'expected_balance' => 125000,
        'status' => 'open',
        'opened_by' => $scenario['agent']->id,
        'opened_at' => now(),
    ]);
    foreach ($scenario['accounts'] as $account) {
        CashLedger::query()->create([
            'cash_session_id' => $session->id,
            'ledger_key' => $account->uuid,
            'kind' => 'mobile_money',
            'agency_payment_account_id' => $account->id,
            'opening_balance' => 0,
            'expected_balance' => 0,
            'status' => 'open',
            'opened_by' => $scenario['agent']->id,
            'opened_at' => now(),
        ]);
    }

    return $session->load('ledgers');
}

it('requires an agency even for administrators on the journal and export', function (): void {
    $admin = User::factory()->create(['agency_id' => null]);
    $admin->assignRole('system_admin');
    $this->actingAs($admin)->get(route('cash'))->assertForbidden();
    $this->get(route('cash.export'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.user.canViewCash', false));
});

it('returns a real empty journal before opening a personal cash session', function (): void {
    $scenario = cashManagementScenario();
    $this->actingAs($scenario['agent'])->get(route('cash'))->assertInertia(fn ($page) => $page
        ->component('cash')->where('cashSession', null)->where('operations.total', 0)
        ->where('permissions.canOpen', true)->where('auth.user.canViewCash', true));
});

it('paginates the entire personal journal while keeping totals independent of filters', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);
    $ledger = $session->ledgers->firstWhere('kind', CashLedgerKind::Cash);
    CashMovement::factory()->count(35)->create(['cash_ledger_id' => $ledger->id, 'amount' => 1000]);
    $oldMovement = CashMovement::query()->oldest('id')->firstOrFail();
    $oldMovement->update(['reference' => 'ANCIENNE-VENTE']);
    CashMovement::factory()->pendingTransfer()->create(['cash_ledger_id' => $ledger->id, 'amount' => 3000]);
    CashMovement::factory()->pendingTransfer()->approved()->create(['cash_ledger_id' => $ledger->id, 'amount' => 2000]);
    $ledger->update(['expected_balance' => 158000]);
    $colleague = User::factory()->create(['agency_id' => $scenario['agency']->id]);
    $foreignLedger = CashLedger::factory()->create();
    CashMovement::factory()->create(['cash_ledger_id' => $foreignLedger->id, 'reference' => 'AUTRE-AGENCE']);
    $colleagueSession = CashSession::factory()->create(['agent_id' => $colleague->id, 'agency_id' => $scenario['agency']->id, 'business_date' => now()->toDateString()]);
    $colleagueLedger = CashLedger::factory()->create(['cash_session_id' => $colleagueSession->id]);
    CashMovement::factory()->create(['cash_ledger_id' => $colleagueLedger->id, 'reference' => 'COLLEGUE']);

    $this->actingAs($scenario['agent'])->get(route('cash', ['page' => 3]))->assertInertia(fn ($page) => $page
        ->has('operations.data', 10)->where('operations.total', 40)->where('operations.last_page', 3)
        ->where('cashSession.ledgers.0.inflows', 35000)->where('cashSession.ledgers.0.outflows', 2000)
        ->where('cashSession.ledgers.0.expectedBalance', 158000)->where('cashSession.ledgers.0.movementCount', 37)
        ->missing('cashSession.id')->missing('operations.data.0.id'));
    $this->get(route('cash', ['q' => 'ANCIENNE-VENTE', 'channel' => 'cash', 'status' => 'posted', 'direction' => 'in']))
        ->assertInertia(fn ($page) => $page->where('operations.total', 1)
            ->where('operations.data.0.uuid', $oldMovement->uuid)->where('cashSession.ledgers.0.inflows', 35000));
    $csv = $this->get(route('cash.export'))->assertOk()->streamedContent();
    expect(substr_count(trim($csv), "\n"))->toBe(38)
        ->and($csv)->toContain('ANCIENNE-VENTE', 'COLLEGUE')->not->toContain('AUTRE-AGENCE');
    $csv = $this->get(route('cash.export', ['q' => 'ANCIENNE-VENTE', 'page' => 3]))->assertOk()->streamedContent();
    expect(substr_count(trim($csv), "\n"))->toBe(1)->and($csv)->toContain('ANCIENNE-VENTE');
});

it('lets an agent review their past closed session operations even without an active session today', function (): void {
    $scenario = cashManagementScenario();
    $yesterday = now()->subDay()->toDateString();
    $pastSession = CashSession::query()->create([
        'agent_id' => $scenario['agent']->id,
        'agency_id' => $scenario['agency']->id,
        'business_date' => $yesterday,
        'status' => 'closed',
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHours(8),
    ]);
    $pastLedger = CashLedger::query()->create([
        'cash_session_id' => $pastSession->id,
        'ledger_key' => 'cash',
        'kind' => 'cash',
        'opening_balance' => 50000,
        'expected_balance' => 50000,
        'status' => 'closed',
        'opened_by' => $scenario['agent']->id,
        'opened_at' => now()->subDay(),
    ]);
    CashMovement::factory()->create([
        'cash_ledger_id' => $pastLedger->id,
        'reference' => 'VENTE-HIER',
        'occurred_at' => now()->subDay()->addHours(4),
    ]);

    $this->actingAs($scenario['agent'])->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->where('cashSession', null)
            ->where('operations.total', 3) // ouverture + clôture + 1 vente hier
            ->where('operations.data.1.reference', 'VENTE-HIER'));
});

it('filters the journal by network and escapes spreadsheet formulas in exports', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);
    $mtn = $session->ledgers->firstWhere('agency_payment_account_id', $scenario['accounts'][0]->id);
    CashMovement::factory()->create(['cash_ledger_id' => $mtn->id, 'reference' => '=1+1']);
    $this->actingAs($scenario['agent'])->get(route('cash', ['channel' => 'mtn_momo', 'q' => 'MTN']))
        ->assertInertia(fn ($page) => $page->where('operations.total', 1));
    $this->get(route('cash', ['channel' => 'moov_flooz']))->assertInertia(fn ($page) => $page->where('operations.total', 0));
    expect($this->get(route('cash.export'))->streamedContent())->toContain("'=1+1");
    $this->getJson(route('cash', ['direction' => 'invalid']))->assertUnprocessable();
});

it('refuses journal access without cash permission and closure outside the personal agency session', function (): void {
    $scenario = cashManagementScenario();
    $session = createCashManagementSession($scenario);
    $user = User::factory()->create(['agency_id' => $scenario['agency']->id]);
    $this->actingAs($user)->get(route('cash'))->assertForbidden();
    $user->assignRole('agency_manager');
    $this->post(route('cash.close', $session), [])->assertForbidden();
    $user->forceFill(['agency_id' => Agency::factory()->create()->id])->save();
    $session->update(['status' => 'pending_approval', 'actual_balance' => 0, 'variance' => -125000]);
    $this->post(route('cash.approve-variance', $session), [])->assertForbidden();
});
