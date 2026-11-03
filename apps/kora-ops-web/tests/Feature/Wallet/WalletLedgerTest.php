<?php

use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->ledger = app(WalletLedgerService::class);
});

it('credits and debits a wallet while keeping materialized balances in sync', function (): void {
    $scenario = walletScenario();

    $this->ledger->credit($scenario['wallet'], WalletBalanceType::Cash, 10000, WalletTransactionReason::TopUp, 'credit-1');
    $this->ledger->debit($scenario['wallet'], WalletBalanceType::Cash, 4000, WalletTransactionReason::Purchase, 'debit-1');

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(6000)
        ->and($this->ledger->reconstructedBalances($scenario['wallet']))->toBe(['cash' => 6000, 'points' => 0]);
});

it('rejects a debit above the available balance', function (): void {
    $scenario = walletScenario();
    $this->ledger->credit($scenario['wallet'], WalletBalanceType::Cash, 5000, WalletTransactionReason::TopUp, 'credit-2');

    expect(fn () => $this->ledger->debit($scenario['wallet'], WalletBalanceType::Cash, 6000, WalletTransactionReason::Purchase, 'debit-2'))
        ->toThrow(ValidationException::class);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and(WalletTransaction::query()->where('idempotency_key', 'debit-2')->exists())->toBeFalse();
});

it('replays an idempotent write instead of double crediting', function (): void {
    $scenario = walletScenario();

    $first = $this->ledger->credit($scenario['wallet'], WalletBalanceType::Cash, 3000, WalletTransactionReason::TopUp, 'same-key');
    $second = $this->ledger->credit($scenario['wallet'], WalletBalanceType::Cash, 3000, WalletTransactionReason::TopUp, 'same-key');

    expect($second->getKey())->toBe($first->getKey())
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(3000)
        ->and(WalletTransaction::query()->where('idempotency_key', 'same-key')->count())->toBe(1);
});

it('subtracts active holds from the available balance', function (): void {
    $scenario = walletScenario(['cash' => 10000]);

    WalletHold::query()->create([
        'wallet_id' => $scenario['wallet']->getKey(),
        'cash_amount' => 7000,
        'points_amount' => 0,
        'status' => WalletHoldStatus::Active,
        'expires_at' => now()->addMinutes(15),
        'idempotency_key' => 'hold-test',
    ]);

    expect($this->ledger->availableBalance($scenario['wallet'], WalletBalanceType::Cash))->toBe(3000);

    expect(fn () => $this->ledger->debit($scenario['wallet'], WalletBalanceType::Cash, 4000, WalletTransactionReason::Purchase, 'debit-held'))
        ->toThrow(ValidationException::class);
});

it('rearms the points expiry window on a qualifying transaction', function (): void {
    $scenario = walletScenario();
    $scenario['wallet']->update(['points_expires_at' => now()->addDay()]);

    $this->ledger->credit($scenario['wallet'], WalletBalanceType::Cash, 2000, WalletTransactionReason::TopUp, 'rearm-1');

    expect($scenario['wallet']->fresh()->points_expires_at->isAfter(now()->addMonths(5)))->toBeTrue();
});
