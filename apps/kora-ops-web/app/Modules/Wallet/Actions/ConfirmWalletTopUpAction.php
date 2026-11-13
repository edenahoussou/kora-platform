<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Crédite le Wallet uniquement après confirmation provider, une seule fois.
 */
final class ConfirmWalletTopUpAction
{
    /**
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     * @param  WalletNotificationPublisher  $notifications  Notifications voyageur
     */
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    /**
     * Crédite le Wallet après confirmation provider, une seule fois.
     *
     * @param  Payment  $payment  Paiement de recharge confirmé
     * @return WalletTopUp|null Recharge créditée, null si le paiement n'en porte pas
     */
    public function confirm(Payment $payment): ?WalletTopUp
    {
        $credited = null;

        $topup = DB::transaction(function () use ($payment, &$credited): ?WalletTopUp {
            $topup = WalletTopUp::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($topup === null || $topup->status === WalletTopUpStatus::Confirmed) {
                return $topup;
            }

            $topup->update(['status' => WalletTopUpStatus::Confirmed]);
            $credited = $topup;

            $this->ledger->credit(
                $topup->wallet,
                WalletBalanceType::Cash,
                (int) $topup->amount,
                WalletTransactionReason::TopUp,
                'topup:'.$topup->uuid,
                ['payment_uuid' => $payment->uuid],
                $payment,
            );

            $cap = app(WalletSettings::class)->cashBalanceCap;
            if ($cap !== null && $topup->wallet->cash_balance > $cap) {
                DB::afterCommit(fn () => Log::warning('Wallet cash cap exceeded after verified top-up', ['topup_uuid' => $topup->uuid]));
            }

            return $topup->fresh();
        });

        if ($credited !== null) {
            $this->notifications->topUpConfirmed($credited->wallet->refresh(), (int) $credited->amount);
        }

        return $topup;
    }

    /**
     * Marque la recharge en échec sans toucher au solde.
     *
     * @param  Payment  $payment  Paiement de recharge échoué
     */
    public function fail(Payment $payment): void
    {
        WalletTopUp::query()
            ->where('payment_id', $payment->getKey())
            ->where('status', WalletTopUpStatus::Pending->value)
            ->update(['status' => WalletTopUpStatus::Failed->value]);
    }
}
