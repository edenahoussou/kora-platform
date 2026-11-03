<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletLedgerService;

/**
 * Route un remboursement vers le Wallet cash du payeur personnel, au prorata
 * du financement initial (points remboursés en points, argent en cash).
 */
final class RouteRefundToWalletAction
{
    public function __construct(
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly WalletLedgerService $ledger,
    ) {}

    /**
     * @return array{wallet: Wallet, points: int, cash: int}|null
     */
    public function handle(Reservation $reservation, Payment $payment, int $amount, string $reference): ?array
    {
        if ($amount <= 0 || $reservation->payer_type !== PayerType::Personal) {
            return null;
        }

        $owner = $reservation->payer;

        if ($owner === null) {
            return null;
        }

        $wallet = $this->findOrCreateWallet->handle($owner);
        $total = max(1, (int) $payment->amount);
        $payload = $payment->provider_payload ?? [];
        $pointsFunded = (int) ($payload['points_amount'] ?? 0);
        $cashFunded = (int) ($payload['wallet_amount'] ?? 0);

        $pointsShare = min($amount, (int) floor($amount * $pointsFunded / $total));
        $cashShare = min($amount - $pointsShare, (int) floor($amount * $cashFunded / $total));
        $cashShare += $amount - $pointsShare - $cashShare;

        if ($pointsShare > 0) {
            $this->ledger->credit(
                $wallet,
                WalletBalanceType::Points,
                $pointsShare,
                WalletTransactionReason::RefundPoints,
                'refund:'.$reference.':points',
                ['reservation_uuid' => $reservation->uuid],
                $reservation,
            );
        }

        if ($cashShare > 0) {
            $this->ledger->credit(
                $wallet,
                WalletBalanceType::Cash,
                $cashShare,
                WalletTransactionReason::RefundCash,
                'refund:'.$reference.':cash',
                ['reservation_uuid' => $reservation->uuid],
                $reservation,
            );
        }

        return ['wallet' => $wallet, 'points' => $pointsShare, 'cash' => $cashShare];
    }
}
