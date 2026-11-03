<?php

use App\Modules\Booking\Actions\CancelReservationAction;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletTransaction;

it('routes a personal refund to wallet cash and points at the initial split', function (): void {
    $scenario = walletScenario();
    walletConfirmedPayment($scenario['reservation'], ['wallet_amount' => 10000, 'points_amount' => 5000]);
    $scenario['reservation']->update(['status' => 'paid']);

    app(CancelReservationAction::class)->cancel($scenario['reservation'], $scenario['user'], true);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(10000)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(5000);
});

it('claws back earned points when the reservation is refunded', function (): void {
    $scenario = walletScenario(['segment_status' => 'boarded']);
    walletConfirmedPayment($scenario['reservation'], ['wallet_amount' => 10000, 'points_amount' => 5000]);
    $scenario['reservation']->update(['status' => 'paid']);

    app(CreditLegPointsAction::class)->handle($scenario['reservation']->fresh());
    expect($scenario['wallet']->fresh()->points_balance)->toBe(700);

    app(CancelReservationAction::class)->cancel($scenario['reservation']->fresh(), $scenario['user'], true);

    expect($scenario['wallet']->fresh()->points_balance)->toBe(5500)
        ->and(WalletTransaction::query()->where('reason', WalletTransactionReason::PointsCorrection->value)->exists())->toBeTrue();
});

it('does not double refund on a repeated cancellation', function (): void {
    $scenario = walletScenario();
    walletConfirmedPayment($scenario['reservation'], ['wallet_amount' => 10000, 'points_amount' => 5000]);
    $scenario['reservation']->update(['status' => 'paid']);

    $action = app(CancelReservationAction::class);
    $action->cancel($scenario['reservation'], $scenario['user'], true);
    $action->cancel($scenario['reservation']->fresh(), $scenario['user'], true);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(10000)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(5000);
});
