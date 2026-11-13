<?php

use App\Modules\Booking\Actions\ExpireSeatHoldsAction;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Actions\InitiatePaymentAction;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\Queue;

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

it('protects wallet hold from early release while reservation is active and payment is pending', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-protect-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    // Même si le hold a son expires_at dépassé (ex: 16 min écoulées),
    // tant que la réservation est encore valide, le hold n'est pas libéré
    $this->travel(16)->minutes();
    // On repousse un peu l'expiration de la réservation pour simuler une réservation encore active
    $scenario['reservation']->update(['expires_at' => now()->addMinutes(5)]);

    $released = app(ReleaseExpiredWalletHoldsAction::class)->handle();
    expect($released)->toBe(0)
        ->and(WalletHold::query()->firstOrFail()->status)->toBe(WalletHoldStatus::Active);
});

it('releases the wallet hold when reservation expires without payment confirmation', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-expire-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    $this->travel(20)->minutes();

    // 1. ExpireSeatHoldsAction clôture la réservation et passe le paiement à Failed
    app(ExpireSeatHoldsAction::class)->handle();

    expect($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($result['payment']->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($result['payment']->fresh()->failure_code)->toBe('reservation_expired');

    // 2. ReleaseExpiredWalletHoldsAction peut maintenant libérer le hold
    $released = app(ReleaseExpiredWalletHoldsAction::class)->handle();
    expect($released)->toBe(1)
        ->and(WalletHold::query()->firstOrFail()->status)->toBe(WalletHoldStatus::Released)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(20000)
        ->and(app(WalletLedgerService::class)->availableBalance($scenario['wallet']->fresh(), WalletBalanceType::Cash))->toBe(20000);
});

it('auto-recovers and captures hold when late payment succeeds if wallet balance and reservation are intact', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-autorecover-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    // Simule une libération manuelle ou accidentelle du hold
    $hold = WalletHold::query()->firstOrFail();
    $hold->update(['status' => WalletHoldStatus::Released]);

    // La confirmation arrive en retard : le solde est toujours là (20 000 F) et la réservation est encore Held
    $confirmed = app(ConfirmPaymentAction::class)->handle($result['payment'], 'success', 'late-receipt-auto');

    expect($confirmed->status)->toBe(PaymentStatus::Confirmed)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Paid)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(10000)
        ->and(WalletHold::query()->where('status', WalletHoldStatus::Captured->value)->count())->toBe(1);

    Queue::assertPushed(IssueReservationTicketsJob::class);
});

it('auto-settles provider payment to passenger wallet cash when late confirmation arrives on expired booking', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-autosettle-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    $this->travel(30)->minutes();
    app(ExpireSeatHoldsAction::class)->handle();
    app(ReleaseExpiredWalletHoldsAction::class)->handle();

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(20000);

    // Le webhook de succès arrive alors que la réservation est déjà expirée
    $confirmed = app(ConfirmPaymentAction::class)->handle($result['payment'], 'success', 'late-provider-success');

    expect($confirmed->status)->toBe(PaymentStatus::Confirmed)
        ->and($confirmed->failure_code)->toBe('booking_expired_settled_to_wallet')
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Expired)
        // Les 5 000 FCFA Mobile Money ont été immédiatement crédités sur le Wallet du client
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(25000);

    // Trace comptable spécifique
    $settlementTx = WalletTransaction::query()
        ->where('wallet_id', $scenario['wallet']->getKey())
        ->where('reason', 'unconfirmed_booking_settlement')
        ->first();

    expect($settlementTx)->not->toBeNull()
        ->and($settlementTx->amount)->toBe(5000)
        ->and($settlementTx->meta['reason'])->toBe('unconfirmed_booking_settlement');

    // Aucun ticket émis
    Queue::assertNotPushed(IssueReservationTicketsJob::class);
});

it('remains idempotent on repeated late payment success webhooks', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);

    $result = app(InitiatePaymentAction::class)->handle(
        reservation: $scenario['reservation'],
        user: $scenario['user'],
        method: 'mobile_money',
        provider: 'fedapay',
        idempotencyKey: 'mixed-idempotent-1',
        phone: '+2290197421860',
        mobileMoneyNetwork: 'mtn_momo',
        walletAmount: 10000,
        mobileMoneyAmount: 5000,
    );

    $this->travel(30)->minutes();
    app(ExpireSeatHoldsAction::class)->handle();
    app(ReleaseExpiredWalletHoldsAction::class)->handle();

    // Premier appel : crédit des 5 000 F
    app(ConfirmPaymentAction::class)->handle($result['payment'], 'success', 'late-prov-1');
    expect($scenario['wallet']->fresh()->cash_balance)->toBe(25000);

    // Deuxième appel (rejeu webhook) : solde inchangé
    app(ConfirmPaymentAction::class)->handle($result['payment'], 'success', 'late-prov-1');
    expect($scenario['wallet']->fresh()->cash_balance)->toBe(25000);

    $count = WalletTransaction::query()
        ->where('wallet_id', $scenario['wallet']->getKey())
        ->where('reason', 'unconfirmed_booking_settlement')
        ->count();

    expect($count)->toBe(1);
});
