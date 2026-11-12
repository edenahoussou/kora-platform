<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\ActivateScheduledWalletLoyaltyRulesAction;
use App\Modules\Wallet\Actions\CreateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Actions\ScheduleWalletLoyaltyRuleAction;
use App\Modules\Wallet\Actions\UpdateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\WalletLoyaltyRuleVersionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function walletOpsUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows DAF, operations directors, and admins to read global wallet views', function (): void {
    $this->seed(WalletLoyaltyRuleVersionSeeder::class);

    foreach (['daf', 'operations_director', 'system_admin'] as $role) {
        $this->actingAs(walletOpsUser($role))
            ->get(route('wallet.dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wallet/ops')
                ->where('view', 'dashboard')
                ->has('dashboard.accountCount')
                ->has('auth.user.walletCapabilities.walletOpsView')
            );

        $this->get(route('wallet.accounts'))->assertSuccessful();
        $this->get(route('wallet.transactions'))->assertSuccessful();
        $this->get(route('wallet.rules'))->assertSuccessful();
    }
});

it('loads dashboard indicators and loyalty rules from current wallet data', function (): void {
    $viewer = walletOpsUser('daf');
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));

    $settings = app(WalletSettings::class);
    $settings->pointValueFcfa = 2;
    $settings->loyaltyEarnRateBasisPoints = 350;
    $settings->pointsExpiryReminderDays = [14, 7, 1];
    $settings->save();

    $wallet = walletForUser(User::factory()->create());
    walletFund($wallet, WalletBalanceType::Cash, 1250);
    walletFund($wallet, WalletBalanceType::Points, 600);
    $wallet->forceFill(['points_expires_at' => now()->addDays(10)])->save();

    WalletTopUp::query()->create([
        'wallet_id' => $wallet->getKey(),
        'amount' => 900,
        'status' => WalletTopUpStatus::Pending,
        'idempotency_key' => 'dashboard:pending-top-up',
    ]);
    WalletRestitution::query()->create([
        'wallet_id' => $wallet->getKey(),
        'amount' => 400,
        'status' => RestitutionStatus::Requested,
        'requested_by_user_id' => $viewer->getKey(),
    ]);

    $this->seed(WalletLoyaltyRuleVersionSeeder::class);

    $this->actingAs($viewer)
        ->get(route('wallet.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.accountCount', 1)
            ->where('dashboard.cashTotal', 1250)
            ->where('dashboard.pointsTotal', 600)
            ->where('dashboard.pointsValue', 1200)
            ->where('dashboard.expiringAccountCount', 1)
            ->where('dashboard.expiringPoints', 600)
            ->where('dashboard.expiringValue', 1200)
            ->where('dashboard.pendingTopUpCount', 1)
            ->where('dashboard.pendingTopUpAmount', 900)
            ->where('dashboard.pendingRestitutionCount', 1)
            ->where('dashboard.pendingRestitutionAmount', 400)
            ->where('activeRules.loyaltyEarnRateBasisPoints', 350)
            ->where('activeRules.pointValueFcfa', 2)
            ->where('activeRuleVersion', 1)
            ->has('dashboard.recentMovements', 2)
        );
});

it('refuses agents on global views before querying or provisioning wallets', function (): void {
    $agent = walletOpsUser('kora_point_agent');
    $before = Wallet::query()->count();

    $this->actingAs($agent)->get(route('wallet.dashboard'))->assertForbidden();
    $this->get(route('wallet.accounts'))->assertForbidden();
    $this->get(route('wallet.transactions'))->assertForbidden();
    $this->get(route('wallet.rules'))->assertForbidden();

    expect(Wallet::query()->count())->toBe($before);
});

it('keeps counter lookup available to agents without provisioning a wallet', function (): void {
    $agent = walletOpsUser('agency_agent');
    $holder = User::factory()->create(['phone' => '+2290198765432']);

    Sanctum::actingAs($agent);
    $this->getJson('/api/v1/ops/wallets/lookup?phone='.urlencode((string) $holder->phone))
        ->assertOk()
        ->assertJsonPath('data', null);

    expect(Wallet::query()->where('user_id', $holder->getKey())->exists())->toBeFalse();
});

it('serves account details with public identifiers and no credential or device fields', function (): void {
    $viewer = walletOpsUser('daf');
    $holder = User::factory()->create();
    $wallet = walletForUser($holder);

    $response = $this->actingAs($viewer)->get(route('wallet.accounts.show', $wallet->uuid));

    $response->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('wallet/ops')
        ->where('account.uuid', $wallet->uuid)
        ->where('account.holder.uuid', $holder->uuid)
        ->missing('account.id')
        ->missing('account.user_id')
        ->missing('account.holder.id')
        ->missing('account.pin')
        ->missing('account.device')
    );

    $filters = [
        'search' => (string) $holder->phone,
        'status' => 'active',
        'balance_type' => 'points',
        'expiration' => 'near',
        'agency_uuid' => (string) Str::uuid(),
        'page' => 2,
        'per_page' => 20,
    ];

    $this->get(route('wallet.accounts.show', ['wallet' => $wallet->uuid, ...$filters]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('wallet.accounts', $filters))
        );
});

it('filters accounts and ledger movements using real wallet attributes', function (): void {
    $viewer = walletOpsUser('daf');
    $cashHolder = User::factory()->create(['phone' => '+2290198765401']);
    $pointsHolder = User::factory()->create(['phone' => '+2290198765402']);
    $cashWallet = walletForUser($cashHolder);
    $pointsWallet = walletForUser($pointsHolder);
    walletFund($cashWallet, WalletBalanceType::Cash, 2000);
    walletFund($pointsWallet, WalletBalanceType::Points, 150);

    $this->actingAs($viewer)
        ->get(route('wallet.accounts', ['balance_type' => 'points', 'search' => $pointsHolder->phone]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 1)
            ->where('accounts.data.0.uuid', $pointsWallet->uuid)
            ->where('filters.balance_type', 'points')
        );

    $this->get(route('wallet.transactions', [
        'balance_type' => 'points',
        'direction' => 'credit',
        'reason' => 'points_earn',
        'search' => $pointsHolder->phone,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movements.total', 1)
            ->where('movements.data.0.walletUuid', $pointsWallet->uuid)
            ->where('movements.data.0.reason', 'points_earn')
        );
});

it('searches ledger and source UUIDs, excludes pending operations, and returns the list context', function (): void {
    $viewer = walletOpsUser('daf');
    $scenario = walletScenario();
    $movement = app(WalletLedgerService::class)->credit(
        $scenario['wallet'],
        WalletBalanceType::Points,
        250,
        WalletTransactionReason::PointsEarn,
        'movement-search:'.Str::uuid(),
        [],
        $scenario['reservation'],
        $viewer,
    );

    WalletTopUp::query()->create([
        'wallet_id' => $scenario['wallet']->getKey(),
        'amount' => 1200,
        'status' => WalletTopUpStatus::Pending,
        'idempotency_key' => 'movement-search:pending:'.Str::uuid(),
    ]);

    $this->actingAs($viewer)
        ->get(route('wallet.transactions'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('movements.total', 1));

    $this->get(route('wallet.transactions', ['search' => $movement->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movements.total', 1)
            ->where('movements.data.0.uuid', $movement->uuid)
            ->where('movements.data.0.actorName', $viewer->name)
            ->where('movements.data.0.source.uuid', $scenario['reservation']->uuid)
            ->missing('movements.data.0.id')
            ->missing('movements.data.0.actor_user_id')
        );

    $this->get(route('wallet.transactions', ['search' => $scenario['reservation']->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movements.total', 1)
            ->where('movements.data.0.uuid', $movement->uuid)
        );

    $filters = [
        'search' => $movement->uuid,
        'balance_type' => 'points',
        'direction' => 'credit',
        'reason' => 'points_earn',
        'page' => 2,
        'per_page' => 20,
    ];

    $this->get(route('wallet.transactions.show', [
        'transaction' => $movement->uuid,
        ...$filters,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnUrl', route('wallet.transactions', $filters))
            ->where('movement.actorName', $viewer->name)
            ->where('movement.loyaltyRuleVersion', null)
            ->missing('movement.id')
            ->missing('movement.actor_user_id')
            ->missing('movement.actor.id')
        );
});

it('filters account agencies by the latest wallet activity', function (): void {
    $viewer = walletOpsUser('daf');
    $wallet = walletForUser(User::factory()->create());
    walletFund($wallet, WalletBalanceType::Cash, 1200);
    walletFund($wallet, WalletBalanceType::Points, 80);

    $olderAgency = Agency::factory()->create();
    $latestAgency = Agency::factory()->create();
    $transactions = $wallet->transactions()->orderBy('id')->get();
    $transactions[0]->forceFill([
        'agency_id' => $olderAgency->getKey(),
        'created_at' => now()->subDays(2),
    ])->save();
    $transactions[1]->forceFill([
        'agency_id' => $latestAgency->getKey(),
        'created_at' => now()->subDay(),
    ])->save();

    $this->actingAs($viewer)
        ->get(route('wallet.accounts', ['agency_uuid' => $olderAgency->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('accounts.total', 0));

    $this->get(route('wallet.accounts', ['agency_uuid' => $latestAgency->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 1)
            ->where('accounts.data.0.uuid', $wallet->uuid)
        );
});

it('keeps empty and paginated account states read-only', function (): void {
    $viewer = walletOpsUser('daf');
    $walletCount = Wallet::query()->count();

    $this->actingAs($viewer)
        ->get(route('wallet.accounts', ['per_page' => 10]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 0)
            ->where('accounts.per_page', 10)
            ->has('accounts.data', 0)
        );

    expect(Wallet::query()->count())->toBe($walletCount);

    for ($index = 0; $index < 11; $index++) {
        walletForUser(User::factory()->create());
    }

    $this->get(route('wallet.accounts', ['per_page' => 10]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 11)
            ->where('accounts.per_page', 10)
            ->where('accounts.last_page', 2)
            ->has('accounts.data', 10)
        );
});

it('initializes one loyalty version from active wallet settings without overwriting it', function (): void {
    $settings = app(WalletSettings::class);
    $settings->loyaltyEarnRateBasisPoints = 375;
    $settings->pointsExpiryMonths = 9;
    $settings->save();

    $this->seed(WalletLoyaltyRuleVersionSeeder::class);
    $version = WalletLoyaltyRuleVersion::query()
        ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
        ->firstOrFail();

    expect($version->rules)
        ->toMatchArray([
            'loyaltyEarnRateBasisPoints' => 375,
            'pointsExpiryMonths' => 9,
        ]);

    $settings->loyaltyEarnRateBasisPoints = 500;
    $settings->save();
    $this->seed(WalletLoyaltyRuleVersionSeeder::class);

    expect(WalletLoyaltyRuleVersion::query()->count())->toBe(1)
        ->and($version->fresh()->rules['loyaltyEarnRateBasisPoints'])->toBe(375)
        ->and($version->fresh()->rules['pointsExpiryMonths'])->toBe(9);
});

it('redirects legacy expiry and reconciliation URLs to the consolidated wallet views', function (): void {
    $this->actingAs(walletOpsUser('operations_director'))
        ->get(route('wallet.expiry'))
        ->assertRedirect(route('wallet.dashboard', ['focus' => 'expiry']));

    $this->get(route('wallet.reconciliation'))
        ->assertRedirect(route('wallet.transactions'));
});

it('simulates rule changes without persistence and activates a scheduled version once', function (): void {
    $daf = walletOpsUser('daf');
    $scenario = walletScenario();
    $wallet = $scenario['wallet'];
    walletFund($wallet, WalletBalanceType::Points, 100);
    $wallet->forceFill(['points_expires_at' => Carbon::parse('2026-12-31 12:00:00')])->save();
    $originalExpiry = $wallet->fresh()->points_expires_at;
    $legacyMovement = WalletTransaction::query()->latest('created_at')->firstOrFail();
    expect($legacyMovement->loyalty_rule_version_id)->toBeNull();
    $this->seed(WalletLoyaltyRuleVersionSeeder::class);

    $this->actingAs($daf)
        ->post(route('wallet.rules.store'))
        ->assertRedirect(route('wallet.rules'));

    $draft = WalletLoyaltyRuleVersion::query()
        ->where('status', WalletLoyaltyRuleVersionStatus::Draft->value)
        ->firstOrFail();
    $rules = $draft->rules;
    $rules['loyaltyEarnRateBasisPoints'] = 400;
    $rules['pointsExpiryMonths'] = 12;

    $this->patch(route('wallet.rules.update', $draft->uuid), ['values' => $rules])
        ->assertRedirect(route('wallet.rules'));

    $beforeMovements = WalletTransaction::query()->count();
    $simulation = $this->postJson(route('wallet.rules.simulate', $draft->uuid), [
        'ticket_amount' => 10000,
        'points_used' => 2000,
        'qualifying_trips' => 4,
        'first_usage' => true,
    ])->assertOk()->assertJsonPath('data.pointsEarned', 320)
        ->assertJsonPath('data.welcomeBonusPoints', 500)
        ->assertJsonPath('data.persisted', false);

    expect(WalletTransaction::query()->count())->toBe($beforeMovements)
        ->and($simulation->json('data.projectedValueFcfa'))->toBe(870);

    $nonNthUsage = $this->postJson(route('wallet.rules.simulate', $draft->uuid), [
        'ticket_amount' => 10000,
        'qualifying_trips' => 5,
    ])->assertOk()->assertJsonPath('data.usageBonusPoints', 0);

    expect($nonNthUsage->json('data.projectedPoints'))->toBe(400)
        ->and(WalletTransaction::query()->count())->toBe($beforeMovements);

    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $this->post(route('wallet.rules.schedule', $draft->uuid), [
        'effective_at' => '2026-11-09T00:00:00+00:00',
    ])->assertRedirect(route('wallet.rules'));

    $scheduled = $draft->fresh();
    $this->patch(route('wallet.rules.update', $draft->uuid), ['values' => $rules])
        ->assertForbidden();

    expect(fn () => app(UpdateWalletLoyaltyRuleDraftAction::class)->execute($draft, $rules))
        ->toThrow(ValidationException::class);

    $secondDraft = app(CreateWalletLoyaltyRuleDraftAction::class)->execute($daf);
    expect(fn () => app(ScheduleWalletLoyaltyRuleAction::class)->execute(
        $secondDraft,
        $daf,
        Carbon::parse('2026-11-10 00:00:00'),
    ))->toThrow(ValidationException::class);

    expect($scheduled->status)->toBe(WalletLoyaltyRuleVersionStatus::Scheduled)
        ->and($wallet->fresh()->points_expires_at->equalTo($originalExpiry))->toBeTrue();

    $activatedAt = Carbon::parse('2026-11-09 00:00:00');
    $activation = app(ActivateScheduledWalletLoyaltyRulesAction::class);
    expect($activation->execute($activatedAt))->toBe(1)
        ->and($activation->execute($activatedAt))->toBe(0)
        ->and(app(WalletSettings::class)->loyaltyEarnRateBasisPoints)->toBe(400)
        ->and(app(WalletSettings::class)->pointsExpiryMonths)->toBe(12)
        ->and($wallet->fresh()->points_expires_at->equalTo($originalExpiry))->toBeTrue();

    $this->travelTo($activatedAt);
    $newMovement = app(WalletLedgerService::class)->credit(
        wallet: $wallet->fresh(),
        type: WalletBalanceType::Points,
        amount: 50,
        reason: WalletTransactionReason::RefundPoints,
        idempotencyKey: (string) Str::uuid(),
        reference: $scenario['reservation'],
    );

    expect($newMovement->loyalty_rule_version_id)->toBe($scheduled->getKey())
        ->and($legacyMovement->fresh()->loyalty_rule_version_id)->toBeNull()
        ->and($wallet->fresh()->points_expires_at->equalTo($activatedAt->copy()->addMonths(12)))->toBeTrue();

    $this->get(route('wallet.transactions.show', $newMovement->uuid))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/ops')
            ->where('movement.uuid', $newMovement->uuid)
            ->where('movement.walletUuid', $wallet->uuid)
            ->where('movement.source.uuid', $scenario['reservation']->uuid)
            ->where('movement.loyaltyRuleVersion.uuid', $scheduled->uuid)
            ->missing('movement.id')
            ->missing('movement.actor_user_id')
        );
});

it('keeps loyalty settings read-only through the settings endpoint', function (): void {
    $admin = walletOpsUser('system_admin');
    $settings = app(WalletSettings::class);
    $originalEarnRate = $settings->loyaltyEarnRateBasisPoints;
    $originalCashEnabled = $settings->walletCashEnabled;

    $this->actingAs($admin)
        ->patch(route('administration.settings.wallet.update'), [
            'values' => [
                'walletCashEnabled' => true,
                'loyaltyEarnRateBasisPoints' => $originalEarnRate + 100,
            ],
        ])
        ->assertSessionHasErrors(['values.loyaltyEarnRateBasisPoints']);

    expect(app(WalletSettings::class)->walletCashEnabled)->toBe($originalCashEnabled)
        ->and(app(WalletSettings::class)->loyaltyEarnRateBasisPoints)->toBe($originalEarnRate);
});
