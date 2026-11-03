<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Crédite le Wallet uniquement après confirmation provider, une seule fois.
 */
final class ConfirmWalletTopUpAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function confirm(Payment $payment): ?WalletTopUp
    {
        return DB::transaction(function () use ($payment): ?WalletTopUp {
            $topup = WalletTopUp::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($topup === null || $topup->status === WalletTopUpStatus::Confirmed) {
                return $topup;
            }

            $topup->update(['status' => WalletTopUpStatus::Confirmed]);

            $this->ledger->credit(
                $topup->wallet,
                WalletBalanceType::Cash,
                (int) $topup->amount,
                WalletTransactionReason::TopUp,
                'topup:'.$topup->uuid,
                ['payment_uuid' => $payment->uuid],
                $payment,
            );

            return $topup->fresh();
        });
    }

    public function fail(Payment $payment): void
    {
        WalletTopUp::query()
            ->where('payment_id', $payment->getKey())
            ->where('status', WalletTopUpStatus::Pending->value)
            ->update(['status' => WalletTopUpStatus::Failed->value]);
    }
}
