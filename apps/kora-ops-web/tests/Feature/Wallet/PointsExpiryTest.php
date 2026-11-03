<?php

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\ExpireWalletPointsAction;
use App\Modules\Wallet\Actions\SendPointsExpiryRemindersAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletPointsExpiryReminder;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('expires the whole points balance after the inactivity window', function (): void {
    $scenario = walletScenario(['points' => 500]);
    $scenario['wallet']->update(['points_expires_at' => now()->subDay()]);

    expect(app(ExpireWalletPointsAction::class)->handle())->toBe(1)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(0)
        ->and(WalletTransaction::query()->where('reason', WalletTransactionReason::PointsExpire->value)->exists())->toBeTrue();
});

it('keeps points that are still inside the window', function (): void {
    $scenario = walletScenario(['points' => 500]);
    $scenario['wallet']->update(['points_expires_at' => now()->addMonth()]);

    expect(app(ExpireWalletPointsAction::class)->handle())->toBe(0)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(500);
});

it('sends the expiry reminder once per milestone', function (): void {
    Carbon::setTestNow('2026-11-03 08:00:00');
    $scenario = walletScenario(['points' => 500]);
    $scenario['wallet']->update(['points_expires_at' => Carbon::now('Africa/Porto-Novo')->addDays(30)->startOfDay()]);

    $action = app(SendPointsExpiryRemindersAction::class);

    expect($action->handle())->toBe(1)
        ->and($action->handle())->toBe(0)
        ->and(WalletPointsExpiryReminder::query()->count())->toBe(1);
});

it('honours the configured reminder milestones', function (): void {
    Carbon::setTestNow('2026-11-03 08:00:00');
    $settings = app(WalletSettings::class);
    $settings->pointsExpiryReminderDays = [10];
    $settings->save();

    $scenario = walletScenario(['points' => 120]);
    $scenario['wallet']->update(['points_expires_at' => Carbon::now('Africa/Porto-Novo')->addDays(10)->startOfDay()]);

    expect(app(SendPointsExpiryRemindersAction::class)->handle())->toBe(1);
});

it('rearms the expiry window when points are refunded', function (): void {
    $scenario = walletScenario(['points' => 0]);
    $scenario['wallet']->update(['points_expires_at' => now()->subDay()]);

    app(WalletLedgerService::class)->credit(
        $scenario['wallet'],
        WalletBalanceType::Points,
        200,
        WalletTransactionReason::RefundPoints,
        'refund-rearm',
    );

    expect($scenario['wallet']->fresh()->points_expires_at->isFuture())->toBeTrue()
        ->and(app(ExpireWalletPointsAction::class)->handle())->toBe(0);
});
