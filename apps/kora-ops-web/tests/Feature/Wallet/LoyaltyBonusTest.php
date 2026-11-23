<?php

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\AttachReferralCodeAction;
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
    $referee = walletScenario();
    walletConfirmedPayment($referee['reservation']);
    app(AttachReferralCodeAction::class)->handle($referee['wallet'], $referrer['wallet']->referral_code);

    $action = app(CreditLegPointsAction::class);
    expect($action->handle($referee['reservation']->fresh()))->toBe(0)
        ->and($referrer['wallet']->refresh()->points_balance)->toBe(0);

    $referee['segment']->update(['status' => 'boarded']);
    $action->handle($referee['reservation']->fresh());
    $action->handle($referee['reservation']->fresh());

    expect($referrer['wallet']->fresh()->points_balance)->toBe(200)
        ->and($referee['wallet']->fresh()->points_balance)->toBe(800);
});

it('grants the anniversary bonus once per year', function (): void {
    $this->travelTo(now('Africa/Porto-Novo')->setDate(2026, 6, 15)->setTime(12, 0));

    $scenario = walletScenario();
    Wallet::query()->whereKey($scenario['wallet']->getKey())->update([
        'created_at' => now('Africa/Porto-Novo')->subYear()->startOfDay(),
        'updated_at' => now('Africa/Porto-Novo')->subYear()->startOfDay(),
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
