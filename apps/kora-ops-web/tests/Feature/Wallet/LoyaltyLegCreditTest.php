<?php

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Models\WalletUsage;
use Illuminate\Support\Facades\Notification;

function walletBoardedScenario(int $pointsFunded = 0): array
{
    $scenario = walletScenario(['segment_status' => 'boarded']);
    walletConfirmedPayment($scenario['reservation'], ['points_amount' => $pointsFunded]);

    return $scenario;
}

it('credits points on the monetary part of a boarded leg', function (): void {
    $scenario = walletBoardedScenario();

    $credited = app(CreditLegPointsAction::class)->handle($scenario['reservation']);

    expect($credited)->toBe(300)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(800);
});

it('excludes the share paid with points from the earning base', function (): void {
    $scenario = walletBoardedScenario(pointsFunded: 3000);

    $credited = app(CreditLegPointsAction::class)->handle($scenario['reservation']);

    expect($credited)->toBe(240)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(740);
});

it('is idempotent when the same leg is credited twice', function (): void {
    $scenario = walletBoardedScenario();
    $action = app(CreditLegPointsAction::class);

    $action->handle($scenario['reservation']);
    $action->handle($scenario['reservation']->fresh());

    expect($scenario['wallet']->fresh()->points_balance)->toBe(800)
        ->and(WalletTransaction::query()
            ->where('reason', WalletTransactionReason::PointsEarn->value)
            ->count())->toBe(1);
});

it('reports and notifies nothing when the daily catch-up replays a credited leg', function (): void {
    Notification::fake();
    $scenario = walletBoardedScenario();
    $action = app(CreditLegPointsAction::class);

    expect($action->handle($scenario['reservation']))->toBe(300);
    Notification::assertSentTimes(PassengerInboxNotification::class, 2);

    expect($action->handle($scenario['reservation']->fresh()))->toBe(0)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(800);

    Notification::assertSentTimes(PassengerInboxNotification::class, 2);
});

it('keeps the deferred compensation when a credited leg is replayed', function (): void {
    $scenario = walletBoardedScenario();
    $action = app(CreditLegPointsAction::class);
    $action->handle($scenario['reservation']);

    Wallet::query()->whereKey($scenario['wallet']->getKey())->update(['points_compensation_due' => 500]);

    expect($action->handle($scenario['reservation']->fresh()))->toBe(0)
        ->and($scenario['wallet']->fresh()->points_compensation_due)->toBe(500);
});

it('does not credit a leg that was never boarded', function (): void {
    $scenario = walletScenario(['segment_status' => 'ticketed']);
    walletConfirmedPayment($scenario['reservation']);

    expect(app(CreditLegPointsAction::class)->handle($scenario['reservation']))->toBe(0);
});

it('does not award an unlinked passenger the personal payer wallet', function (): void {
    $scenario = walletBoardedScenario();
    $scenario['passenger']->update(['user_id' => null]);

    expect(app(CreditLegPointsAction::class)->handle($scenario['reservation']->fresh()))->toBe(0)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(0)
        ->and(WalletUsage::query()->count())->toBe(0);
});

it('calculates loyalty from the frozen discounted leg allocation', function (): void {
    $scenario = walletBoardedScenario();
    $scenario['reservation']->update([
        'total_amount' => 7500,
        'total_snapshot' => [
            'legs' => [['leg_order' => 1, 'final_amount' => 10000]],
            'passenger_allocations' => [7500],
            'passenger_leg_allocations' => [[7500]],
        ],
    ]);
    $scenario['reservation']->payments()->firstOrFail()->update(['amount' => 7500]);

    expect(app(CreditLegPointsAction::class)->handle($scenario['reservation']->fresh()))->toBe(150)
        ->and(WalletTransaction::query()
            ->where('reason', WalletTransactionReason::PointsEarn->value)
            ->value('amount'))->toBe(150);
});

it('catches up late arrivals through the scheduled command', function (): void {
    $scenario = walletBoardedScenario();
    $scenario['departure']->update([
        'status' => DepartureStatus::Arrived,
        'arrived_at' => now(),
    ]);

    $this->artisan('wallet:credit-pending-legs')->assertSuccessful();

    expect($scenario['wallet']->fresh()->points_balance)->toBe(800);
});
