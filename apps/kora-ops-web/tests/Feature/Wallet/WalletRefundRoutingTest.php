<?php

use App\Models\User;
use App\Modules\Booking\Actions\CancelReservationAction;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Wallet\Actions\ApplyLoyaltyCorrectionAction;
use App\Modules\Wallet\Actions\CreditLegPointsAction;
use App\Modules\Wallet\Actions\RouteRefundToWalletAction;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
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

it('limits an individual passenger loyalty correction to that passenger', function (): void {
    $scenario = walletScenario(['total' => 20000, 'segment_status' => 'boarded']);
    $otherUser = User::factory()->create(['phone' => '+22901'.random_int(1000000, 9999999)]);
    $otherWallet = walletForUser($otherUser);
    $otherPassenger = ReservationPassenger::query()->create([
        'reservation_id' => $scenario['reservation']->getKey(),
        'user_id' => $otherUser->getKey(),
        'status' => 'active',
        'passenger_index' => 1,
        'first_name' => 'Aminata',
        'last_name' => 'Diallo',
        'phone' => $otherUser->phone,
    ]);
    ReservationSegment::query()->create([
        'reservation_id' => $scenario['reservation']->getKey(),
        'reservation_passenger_id' => $otherPassenger->getKey(),
        'leg_order' => 1,
        'passenger_index' => 1,
        'departure_id' => $scenario['departure']->getKey(),
        'seat_id' => $scenario['seat']->getKey(),
        'origin_stop_id' => $scenario['segment']->origin_stop_id,
        'destination_stop_id' => $scenario['segment']->destination_stop_id,
        'origin_stop_order' => $scenario['segment']->origin_stop_order,
        'destination_stop_order' => $scenario['segment']->destination_stop_order,
        'status' => 'boarded',
    ]);
    $scenario['reservation']->update([
        'total_snapshot' => [
            'legs' => [['leg_order' => 1, 'final_amount' => 20000]],
            'passenger_allocations' => [10000, 10000],
            'passenger_leg_allocations' => [[10000], [10000]],
        ],
    ]);
    walletConfirmedPayment($scenario['reservation'], amount: 20000);
    app(CreditLegPointsAction::class)->handle($scenario['reservation']->fresh());

    app(ApplyLoyaltyCorrectionAction::class)->handle(
        $scenario['reservation']->fresh(),
        10000,
        $scenario['passenger'],
    );

    expect(WalletTransaction::query()
        ->where('wallet_id', $scenario['wallet']->getKey())
        ->where('reason', WalletTransactionReason::PointsCorrection->value)
        ->sum('amount'))->toBe(200)
        ->and(WalletTransaction::query()
            ->where('wallet_id', $otherWallet->getKey())
            ->where('reason', WalletTransactionReason::PointsCorrection->value)
            ->count())->toBe(0);
});

it('carries the partial point refund remainder at the frozen rate and never turns it into cash', function (): void {
    $scenario = walletScenario(['total' => 9]);
    $payment = walletConfirmedPayment($scenario['reservation']);
    $payment->update(['wallet_funding' => WalletPurchaseFunding::purchase(9, 0, 3, 3)->toArray()]);
    $action = app(RouteRefundToWalletAction::class);
    $first = $action->handle($scenario['reservation'], $payment, 5, 'first');
    expect($first['points'])->toBe(1)->and($first['cash'])->toBe(0);
    $payment->refunds()->create([
        'reservation_id' => $scenario['reservation']->getKey(),
        'amount' => 5, 'currency' => 'XOF', 'destination' => 'wallet_cash', 'status' => 'completed',
        'reference' => 'first', 'idempotency_key' => 'refund-first', 'metadata' => ['wallet_points' => 1, 'wallet_cash' => 0],
    ]);
    $second = $action->handle($scenario['reservation'], $payment, 4, 'second');
    expect($second['points'])->toBe(2)->and($second['cash'])->toBe(0)
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(3);
});
