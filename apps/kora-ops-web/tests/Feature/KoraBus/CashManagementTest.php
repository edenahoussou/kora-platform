<?php

use App\Models\User;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
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

/** @return array{kind: string, account_uuid: string|null, opening_balance: int, evidence: UploadedFile} */
function cashOpeningRow(int $balance, ?string $accountUuid = null): array
{
    return [
        'kind' => $accountUuid === null ? 'cash' : 'mobile_money',
        'account_uuid' => $accountUuid,
        'opening_balance' => $balance,
        'evidence' => UploadedFile::fake()->image('opening.jpg'),
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
