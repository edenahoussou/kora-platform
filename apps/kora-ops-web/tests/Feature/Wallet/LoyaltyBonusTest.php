<?php

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Actions\GrantAnniversaryBonusesAction;
use App\Modules\Wallet\Models\Wallet;

function walletUsageScenario(): array
{
    $scenario = walletScenario(['segment_status' => 'boarded']);
    walletConfirmedPayment($scenario['reservation']);

    return $scenario;
}

it('grants the welcome bonus once at the first completed leg', function (): void {
    $scenario = walletUsageScenario();
    $action = app(CreditLegPointsAction::class);

    $action->handle($scenario['reservation']);
    $action->handle($scenario['reservation']->fresh());

    expect($scenario['wallet']->fresh()->points_balance)->toBe(800);
});

it('grants the nth-usage bonus once per calendar month', function (): void {
    $settings = app(WalletSettings::class);
    $settings->bonusUsageNth = 1;
    $settings->bonusWelcomePoints = 0;
    $settings->save();

    $scenario = walletUsageScenario();
    $action = app(CreditLegPointsAction::class);

    $action->handle($scenario['reservation']);
    $action->handle($scenario['reservation']->fresh());

    expect($scenario['wallet']->fresh()->points_balance)->toBe(350);
});

it('grants the referral bonus to the referrer at the referee first use', function (): void {
    $referrer = walletScenario();
    $referee = walletUsageScenario();
    $referee['wallet']->update(['referred_by_user_id' => $referrer['user']->getKey()]);

    app(CreditLegPointsAction::class)->handle($referee['reservation']->fresh());

    expect($referrer['wallet']->fresh()->points_balance)->toBe(200)
        ->and($referee['wallet']->fresh()->points_balance)->toBe(800);
});

it('grants the anniversary bonus once per year', function (): void {
    $scenario = walletScenario();
    Wallet::query()->whereKey($scenario['wallet']->getKey())->update([
        'created_at' => now()->subYear()->startOfDay(),
        'updated_at' => now()->subYear()->startOfDay(),
    ]);

    $action = app(GrantAnniversaryBonusesAction::class);

    expect($action->handle())->toBe(1)
        ->and($action->handle())->toBe(0)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(100);
});

it('absorbs the compensation due on future loyalty earnings', function (): void {
    $scenario = walletUsageScenario();
    Wallet::query()->whereKey($scenario['wallet']->getKey())->update(['points_compensation_due' => 100]);

    app(CreditLegPointsAction::class)->handle($scenario['reservation']);

    expect($scenario['wallet']->fresh()->points_balance)->toBe(700)
        ->and($scenario['wallet']->fresh()->points_compensation_due)->toBe(0);
});
