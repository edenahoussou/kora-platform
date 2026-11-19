<?php

use App\Modules\Booking\Actions\ExpireSeatHoldsAction;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Actions\InitiatePaymentAction;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\WalletHold;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Queue::fake([IssueReservationTicketsJob::class]);

    $this->app->bind(PaymentManager::class, function (): PaymentManager {
        $manager = new PaymentManager;

        $reflection = new ReflectionClass($manager);
        $reflection->getProperty('resolved')->setValue($manager, [
            'fedapay' => new FakePaymentProvider,
            'mtn_momo' => new FakePaymentProvider,
            'kkiapay' => new FakePaymentProvider,
            'fake' => new FakePaymentProvider,
        ]);

        return $manager;
    });

    FakePaymentProvider::reset();
});

it('debits the wallet immediately for a full wallet payment', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'wallet',
        provider: null,
        idempotencyKey: 'wallet-payment-1',
        walletAmount: 15000,
    );

    expect($result['payment']->status)->toBe(PaymentStatus::Confirmed)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and($scenario['reservation']->fresh()->status->value)->toBe('paid')
        ->and(WalletHold::query()->where('status', WalletHoldStatus::Captured->value)->count())->toBe(1);
});

it('holds the wallet share of a mixed payment and captures it on confirmation', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-payment-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    $hold = WalletHold::query()->firstOrFail();
    expect($hold->status)->toBe(WalletHoldStatus::Active)
        ->and($hold->cash_amount)->toBe(10000)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(20000);

    app(ConfirmPaymentAction::class)->handle($result['payment'], 'success', 'MIXED-REF');

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(10000)
        ->and($hold->fresh()->status)->toBe(WalletHoldStatus::Captured);
});

it('releases the wallet hold when the provider payment fails', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'failed-payment-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    app(ConfirmPaymentAction::class)->handle($result['payment'], 'failed');

    expect(WalletHold::query()->firstOrFail()->status)->toBe(WalletHoldStatus::Released)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(20000);
});

it('rejects a wallet payment above the available balance', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 5000]);

    expect(fn () => app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'wallet',
        provider: null,
        idempotencyKey: 'wallet-payment-2',
        walletAmount: 15000,
    ))->toThrow(ValidationException::class);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000);
});

it('rejects points below the configured minimum spend', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000, 'points' => 500]);

    expect(fn () => app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'wallet',
        provider: null,
        idempotencyKey: 'wallet-payment-3',
        walletAmount: 14900,
        pointsAmount: 50,
    ))->toThrow(ValidationException::class);
});

it('never lets two holds exceed the available balance', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 15000]);

    $first = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'concurrent-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    $secondReservation = $scenario['reservation']->replicate(['uuid', 'reference']);
    $secondReservation->status = 'held';
    $secondReservation->save();

    expect(fn () => app(InitiatePaymentAction::class)->handle(
        reservation: $secondReservation,
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'concurrent-2',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    ))->toThrow(ValidationException::class);

    expect(WalletHold::query()->where('status', WalletHoldStatus::Active->value)->count())->toBe(1)
        ->and($first['payment']->status)->toBe(PaymentStatus::Pending);
});

it('freezes the point rate in the purchase and does not issue an underfunded ticket after hold expiry', function (): void {
    walletCashEnabled();
    $settings = app(WalletSettings::class);
    $settings->pointValueFcfa = 3;
    $settings->save();
    $scenario = walletScenario(['cash' => 1000, 'points' => 1000]);

    $payment = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-point-value',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 1000,
        pointsAmount: 1000,
        mobileMoneyAmount: 11000,
    )['payment'];
    expect($payment->wallet_funding)->toMatchArray([
        'points_amount' => 1000, 'points_value_fcfa' => 3000, 'point_value_fcfa' => 3, 'provider_amount_fcfa' => 11000,
    ]);

    $settings->pointValueFcfa = 4;
    $settings->save();
    $this->travel(30)->minutes();
    app(ExpireSeatHoldsAction::class)->handle();
    app(ReleaseExpiredWalletHoldsAction::class)->handle();
    $updated = app(ConfirmPaymentAction::class)->handle($payment, 'success', 'late-receipt');
    expect($updated->status)->toBe(PaymentStatus::Confirmed)
        ->and($updated->failure_code)->toBe('booking_expired_settled_to_wallet')
        ->and($scenario['wallet']->fresh()->points_balance)->toBe(1000)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(12000)
        ->and($scenario['reservation']->fresh()->status->value)->toBe('expired');
});
