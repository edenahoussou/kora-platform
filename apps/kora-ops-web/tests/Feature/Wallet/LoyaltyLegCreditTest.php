<?php

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletTransaction;

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

it('does not credit a leg that was never boarded', function (): void {
    $scenario = walletScenario(['segment_status' => 'ticketed']);
    walletConfirmedPayment($scenario['reservation']);

    expect(app(CreditLegPointsAction::class)->handle($scenario['reservation']))->toBe(0);
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
