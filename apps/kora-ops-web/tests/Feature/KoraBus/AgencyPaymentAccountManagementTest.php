<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Cash\Actions\OpenCashSessionAction;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Services\CashRegisterBalance;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Storage::fake('private');
});

it('renders an empty agency payment account state with public identifiers only', function (): void {
    $agency = Agency::factory()->create();
    $viewer = User::factory()->create();
    $viewer->assignRole('system_admin');

    $response = $this->actingAs($viewer)
        ->get(route('agencies.show', $agency));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('agencies/detail')
            ->where('agency.uuid', $agency->uuid)
            ->where('paymentAccounts', [])
            ->missing('paymentAccounts.0.id')
            ->missing('paymentAccounts.0.agencyId'));

    expect(route('agencies.show', $agency))->not->toContain('/'.$agency->id);
});

it('creates the first active shared Mobile Money account from the agency page', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);

    $this->actingAs($manager)
        ->post(route('agencies.payment-accounts.store', $agency), [
            'network' => 'mtn_momo',
            'label' => 'MTN agence principale',
            'account_identifier' => '+22997000001',
        ])
        ->assertRedirect(route('agencies.show', $agency, absolute: false));

    $account = AgencyPaymentAccount::query()->firstOrFail();

    expect($account->agency_id)->toBe($agency->id)
        ->and($account->network->value)->toBe('mtn_momo')
        ->and($account->getRawOriginal('status'))->toBe('active')
        ->and(app(CashRegisterBalance::class)->mobileMoneyPublished($account))->toBe(0);
});

it('requires agencies.update for Mobile Money account administration', function (): void {
    $agency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)
        ->post(route('agencies.payment-accounts.store', $agency), [
            'network' => 'mtn_momo',
            'label' => 'Compte interdit',
            'account_identifier' => '+22997000002',
        ])
        ->assertForbidden();

    expect(AgencyPaymentAccount::query()->count())->toBe(0);
});

it('updates an account label without changing its financial identity', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $account = paymentAccount($agency, ['label' => 'Ancien libellé']);

    $this->actingAs($manager)
        ->put(route('agencies.payment-accounts.update', [
            'agency' => $agency,
            'account' => $account,
        ]), ['label' => 'Compte MTN Cotonou'])
        ->assertRedirect(route('agencies.show', $agency, absolute: false));

    expect($account->refresh()->label)->toBe('Compte MTN Cotonou')
        ->and($account->uuid)->not->toBeEmpty()
        ->and(AgencyPaymentAccount::query()->count())->toBe(1);
});

it('deactivates an active account when no session uses it', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $account = paymentAccount($agency);

    $this->actingAs($manager)
        ->patch(route('agencies.payment-accounts.status', [
            'agency' => $agency,
            'account' => $account,
        ]), ['status' => 'inactive'])
        ->assertRedirect(route('agencies.show', $agency, absolute: false));

    expect($account->refresh()->getRawOriginal('status'))->toBe('inactive');
});

it('replaces an active account atomically and preserves its payments, ledgers, and balance', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $oldAccount = paymentAccount($agency, [
        'label' => 'MTN historique',
        'account_identifier' => '+22997000003',
    ]);
    $ledger = mobileMoneyLedger($oldAccount, $manager, 10000, 'closed');
    CashMovement::query()->create([
        'cash_session_id' => $ledger->cash_session_id,
        'cash_ledger_id' => $ledger->id,
        'cash_register_id' => null,
        'type' => 'counter_sale_mobile_money',
        'direction' => 'in',
        'status' => 'posted',
        'amount' => 5000,
        'currency' => 'XOF',
        'description' => 'Encaissement historique',
        'occurred_at' => now(),
    ]);
    $reservation = Reservation::query()->create([
        'status' => 'ticketed',
        'total_amount' => 5000,
        'currency' => 'XOF',
        'channel' => 'counter',
    ]);
    $payment = Payment::query()->create([
        'reservation_id' => $reservation->id,
        'method' => 'mobile_money',
        'agency_payment_account_id' => $oldAccount->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MTN-HIST-001',
        'idempotency_key' => 'payment-'.Str::uuid(),
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    $this->actingAs($manager)
        ->post(route('agencies.payment-accounts.store', $agency), [
            'network' => 'mtn_momo',
            'label' => 'MTN nouveau compte',
            'account_identifier' => '+22997000004',
            'replace_account_uuid' => $oldAccount->uuid,
        ])
        ->assertRedirect(route('agencies.show', $agency, absolute: false));

    $newAccount = AgencyPaymentAccount::query()
        ->where('account_identifier', '+22997000004')
        ->firstOrFail();

    expect($oldAccount->refresh()->getRawOriginal('status'))->toBe('inactive')
        ->and($newAccount->getRawOriginal('status'))->toBe('active')
        ->and($newAccount->uuid)->not->toBe($oldAccount->uuid)
        ->and($newAccount->created_at)->not->toBeNull()
        ->and(app(CashRegisterBalance::class)->mobileMoneyPublished($oldAccount))->toBe(15000)
        ->and(app(CashRegisterBalance::class)->mobileMoneyPublished($newAccount))->toBe(0)
        ->and($payment->refresh()->agency_payment_account_id)->toBe($oldAccount->id)
        ->and(CashLedger::query()->whereKey($ledger->id)->exists())->toBeTrue()
        ->and(AgencyPaymentAccount::query()->where('agency_id', $agency->id)->count())->toBe(2);
});

it('rejects replacement while an open session uses the active account', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $oldAccount = paymentAccount($agency, ['account_identifier' => '+22997000005']);
    mobileMoneyLedger($oldAccount, $manager, 0, 'open');

    $this->actingAs($manager)
        ->post(route('agencies.payment-accounts.store', $agency), [
            'network' => 'mtn_momo',
            'label' => 'Compte bloqué',
            'account_identifier' => '+22997000006',
            'replace_account_uuid' => $oldAccount->uuid,
        ])
        ->assertSessionHasErrors('account');

    expect($oldAccount->refresh()->getRawOriginal('status'))->toBe('active')
        ->and(AgencyPaymentAccount::query()->count())->toBe(1);
});

it('requires an explicit replacement and enforces one active account per network', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $activeAccount = paymentAccount($agency, ['account_identifier' => '+22997000007']);

    $this->actingAs($manager)
        ->post(route('agencies.payment-accounts.store', $agency), [
            'network' => 'mtn_momo',
            'label' => 'Deuxième compte',
            'account_identifier' => '+22997000008',
        ])
        ->assertSessionHasErrors('replace_account_uuid');

    expect($activeAccount->refresh()->getRawOriginal('status'))->toBe('active')
        ->and(AgencyPaymentAccount::query()->count())->toBe(1);
});

it('reactivates a historical account through a guided switch', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $historical = paymentAccount($agency, [
        'label' => 'MTN historique',
        'account_identifier' => '+22997000009',
        'status' => 'inactive',
    ]);
    $current = paymentAccount($agency, [
        'label' => 'MTN courant',
        'account_identifier' => '+22997000010',
    ]);

    $this->actingAs($manager)
        ->patch(route('agencies.payment-accounts.status', [
            'agency' => $agency,
            'account' => $historical,
        ]), [
            'status' => 'active',
            'replace_account_uuid' => $current->uuid,
        ])
        ->assertRedirect(route('agencies.show', $agency, absolute: false));

    expect($historical->refresh()->getRawOriginal('status'))->toBe('active')
        ->and($current->refresh()->getRawOriginal('status'))->toBe('inactive');
});

it('does not include a historical account in new cash sessions', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $register = CashRegister::factory()->for($agency)->create();
    $historical = paymentAccount($agency, [
        'account_identifier' => '+22997000011',
        'status' => 'inactive',
    ]);
    $active = paymentAccount($agency, ['account_identifier' => '+22997000012']);

    $session = app(OpenCashSessionAction::class)->execute($manager, [
        'cash_register_uuid' => $register->uuid,
        'ledgers' => [
            mobileMoneyCashOpeningRow(),
            mobileMoneyCashOpeningRow($active->uuid, CashLedgerKind::MobileMoney->value),
        ],
    ]);

    expect($session->ledgers()->where('agency_payment_account_id', $active->id)->exists())->toBeTrue()
        ->and($session->ledgers()->where('agency_payment_account_id', $historical->id)->exists())->toBeFalse()
        ->and(AgencyPaymentAccount::query()->active()->whereKey($active->id)->exists())->toBeTrue();
});

it('counts an inactive account balance once at agency level without double-counting session ledgers', function (): void {
    $agency = Agency::factory()->create();
    $manager = mobileMoneyManager($agency);
    $account = paymentAccount($agency, [
        'account_identifier' => '+22997000013',
        'status' => 'inactive',
    ]);
    mobileMoneyLedger($account, $manager, 12000, 'closed');
    mobileMoneyLedger($account, $manager, 12000, 'closed');

    expect(app(CashRegisterBalance::class)->agencyTotals($agency->id)['mobile_money'])->toBe(12000);
});

/** @param array<string, mixed> $overrides */
function paymentAccount(Agency $agency, array $overrides = []): AgencyPaymentAccount
{
    return AgencyPaymentAccount::query()->create(array_merge([
        'agency_id' => $agency->id,
        'network' => 'mtn_momo',
        'label' => 'Compte MTN agence',
        'account_identifier' => '+22997000000'.fake()->unique()->numberBetween(1, 9),
        'status' => 'active',
    ], $overrides));
}

function mobileMoneyManager(Agency $agency): User
{
    $manager = User::factory()->create(['agency_id' => $agency->id]);
    $manager->assignRole('system_admin');

    return $manager;
}

function mobileMoneyCashOpeningRow(?string $accountUuid = null, string $kind = 'cash'): array
{
    return [
        'kind' => $kind,
        'account_uuid' => $accountUuid,
        'opening_balance' => 0,
        'opening_comment' => null,
        'evidence' => UploadedFile::fake()->image($kind.'-opening.jpg'),
        'evidence_captured_at' => now()->toISOString(),
    ];
}

function mobileMoneyLedger(
    AgencyPaymentAccount $account,
    User $agent,
    int $openingBalance,
    string $status,
): CashLedger {
    $isClosed = $status === CashLedgerStatus::Closed->value;
    $session = CashSession::query()->create([
        'agent_id' => $agent->id,
        'agency_id' => $account->agency_id,
        'cash_register_id' => null,
        'business_date' => now()->toDateString(),
        'opening_float' => $openingBalance,
        'total_cash' => 0,
        'total_mobile_money' => 0,
        'expected_balance' => $openingBalance,
        'actual_balance' => $isClosed ? $openingBalance : null,
        'variance' => $isClosed ? 0 : null,
        'status' => $isClosed ? CashSessionStatus::Closed : CashSessionStatus::Open,
        'opened_at' => now(),
        'closed_at' => $isClosed ? now() : null,
    ]);

    return CashLedger::query()->create([
        'cash_session_id' => $session->id,
        'ledger_key' => $account->uuid,
        'kind' => CashLedgerKind::MobileMoney,
        'agency_payment_account_id' => $account->id,
        'opening_balance' => $openingBalance,
        'expected_balance' => $openingBalance,
        'actual_balance' => $isClosed ? $openingBalance : null,
        'variance' => $isClosed ? 0 : null,
        'status' => $isClosed ? CashLedgerStatus::Closed : CashLedgerStatus::Open,
        'opened_by' => $agent->id,
        'opened_at' => now(),
        'closed_at' => $isClosed ? now() : null,
    ]);
}
