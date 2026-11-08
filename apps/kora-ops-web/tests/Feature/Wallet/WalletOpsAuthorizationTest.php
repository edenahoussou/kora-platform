<?php

use App\Models\User;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\ActivateScheduledWalletLoyaltyRulesAction;
use App\Modules\Wallet\Actions\CreateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Actions\ScheduleWalletLoyaltyRuleAction;
use App\Modules\Wallet\Actions\UpdateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
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
