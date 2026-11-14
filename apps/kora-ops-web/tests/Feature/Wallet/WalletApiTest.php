<?php

use App\Models\User;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Models\Wallet;
use Laravel\Sanctum\Sanctum;

it('exposes the traveler wallet with balances and ux settings', function (): void {
    $scenario = walletScenario(['cash' => 4000, 'points' => 250]);
    Sanctum::actingAs($scenario['user']);

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('data.cash_balance', 4000)
        ->assertJsonPath('data.points_balance', 250)
        ->assertJsonPath('data.referral_code', $scenario['wallet']->referral_code)
        ->assertJsonPath('data.settings.wallet_cash_enabled', false)
        ->assertJsonPath('data.settings.points_min_spend', 100)
        ->assertJsonPath('data.settings.points_expiry_reminder_days', [30, 7, 1])
        ->assertJsonPath('data.settings.loyalty_bonuses.welcome_points', 500)
        ->assertJsonPath('data.settings.topup_amount_presets', [2000, 5000, 10000, 20000]);
});

it('provisions a wallet on first read for a traveler without one', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/wallet')->assertOk();

    expect(Wallet::query()->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('lists wallet transactions filtered by balance type', function (): void {
    $scenario = walletScenario(['cash' => 5000, 'points' => 300]);
    Sanctum::actingAs($scenario['user']);

    $this->getJson('/api/v1/wallet/transactions?balance_type=points')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.balance_type', WalletBalanceType::Points->value)
        ->assertJsonPath('data.0.amount', 300);

    $this->getJson('/api/v1/wallet/transactions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('attaches a referral code once and refuses self-referral', function (): void {
    $referrer = walletScenario();
    $referee = walletScenario();
    Sanctum::actingAs($referee['user']);

    $this->postJson('/api/v1/wallet/referral', ['referral_code' => $referrer['wallet']->referral_code])
        ->assertOk()
        ->assertJsonPath('data.referred_by', true);

    $this->postJson('/api/v1/wallet/referral', ['referral_code' => $referrer['wallet']->referral_code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referral_code']);

    expect($referrer['user']->notifications()->sole()->data['type'])->toBe('wallet_referral_attached')
        ->and($referee['user']->notifications()->count())->toBe(0);

    Sanctum::actingAs($referrer['user']);
    $this->postJson('/api/v1/wallet/referral', ['referral_code' => $referrer['wallet']->referral_code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referral_code']);
});

it('rejects referral when a boarded ticket has already counted as a wallet usage', function (): void {
    $referrer = walletForUser(User::factory()->create());
    $referee = walletScenario(['segment_status' => 'boarded']);
    app(CreditLegPointsAction::class)->handle($referee['reservation']);

    Sanctum::actingAs($referee['user']);
    $this->postJson('/api/v1/wallet/referral', ['referral_code' => $referrer->referral_code])
        ->assertUnprocessable()
        ->assertJsonPath('errors.referral_code.0', 'Le code de parrainage doit être saisi avant la première utilisation.');

    expect($referee['wallet']->refresh()->referred_by_user_id)->toBeNull()
        ->and($referrer->user->notifications()->count())->toBe(0);
});

it('rejects an unknown referral code', function (): void {
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/wallet/referral', ['referral_code' => 'KUNKNOWN'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referral_code']);
});

it('keeps validated directions and balance filters across cursor pages', function (): void {
    $wallet = walletForUser(User::factory()->create());
    for ($index = 0; $index < 21; $index++) {
        walletFund($wallet, WalletBalanceType::Cash, 1);
    }
    walletFund($wallet, WalletBalanceType::Points, 2);
    Sanctum::actingAs($wallet->user);

    $next = $this->getJson('/api/v1/wallet/transactions?balance_type=cash&direction=credit')
        ->assertOk()->assertJsonCount(20, 'data')->json('links.next');
    expect($next)->toContain('direction=credit', 'balance_type=cash');
    $this->getJson($next)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.direction', 'credit');
    $this->getJson('/api/v1/wallet/transactions?direction=invalid')->assertUnprocessable();
});

it('does not expose the internal points compensation debt to the traveler', function (): void {
    $scenario = walletScenario(['points' => 100]);
    $scenario['wallet']->update(['points_compensation_due' => 400]);
    Sanctum::actingAs($scenario['user']);

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonMissingPath('data.points_compensation_due');
});
