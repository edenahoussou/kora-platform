<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Carbon;

/**
 * Expire globalement les points d'un Wallet resté sans activité qualifiante.
 */
final class ExpireWalletPointsAction
{
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly WalletLedgerService $ledger,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    public function handle(): int
    {
        $expired = 0;

        Wallet::query()
            ->where('points_balance', '>', 0)
            ->chunkById(200, function ($wallets) use (&$expired): void {
                foreach ($wallets as $wallet) {
                    if ($this->expireWallet($wallet)) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    public function expiresAt(Wallet $wallet): Carbon
    {
        if ($wallet->points_expires_at !== null) {
            return Carbon::instance($wallet->points_expires_at);
        }

        $lastQualifying = WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereIn('reason', [
                WalletTransactionReason::Purchase->value,
                WalletTransactionReason::TopUp->value,
                WalletTransactionReason::RefundPoints->value,
            ])
            ->max('created_at');

        $base = $lastQualifying !== null
            ? Carbon::parse((string) $lastQualifying)
            : Carbon::instance($wallet->created_at);

        return $base->copy()->addMonths($this->settings->pointsExpiryMonths);
    }

    private function expireWallet(Wallet $wallet): bool
    {
        $expiresAt = $this->expiresAt($wallet);

        if ($expiresAt->isFuture()) {
            return false;
        }

        $points = (int) $wallet->points_balance;

        if ($points <= 0) {
            return false;
        }

        $this->ledger->debit(
            $wallet,
            WalletBalanceType::Points,
            $points,
            WalletTransactionReason::PointsExpire,
            'points:expire:'.$wallet->uuid.':'.$expiresAt->toDateString(),
            ['expired_at' => $expiresAt->toIso8601String()],
        );

        $wallet->refresh();
        $wallet->update(['points_expires_at' => null]);

        $this->notifications->pointsExpired($wallet, $points);

        return true;
    }
}
