<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;

/**
 * Reprend les points indus après un remboursement, au prorata du montant rendu.
 */
final class ApplyLoyaltyCorrectionAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function handle(Reservation $reservation, ?int $refundedAmount = null): void
    {
        $total = (int) $reservation->total_amount;
        $ratio = $refundedAmount === null || $total <= 0
            ? 1.0
            : min(1.0, $refundedAmount / $total);

        if ($ratio <= 0) {
            return;
        }

        $earned = WalletTransaction::query()
            ->where('reference_type', $reservation->getMorphClass())
            ->where('reference_id', $reservation->getKey())
            ->where('reason', WalletTransactionReason::PointsEarn->value)
            ->selectRaw('wallet_id, SUM(amount) as total')
            ->groupBy('wallet_id')
            ->get();

        foreach ($earned as $row) {
            $wallet = Wallet::query()->find($row->wallet_id);

            if ($wallet === null) {
                continue;
            }

            $toRevoke = (int) floor((int) $row->getAttribute('total') * $ratio);

            if ($toRevoke <= 0) {
                continue;
            }

            $this->ledger->correctPoints(
                $wallet,
                $toRevoke,
                'loyalty:correction:'.$reservation->uuid.':'.($refundedAmount ?? 'full'),
                ['reservation_uuid' => $reservation->uuid, 'refunded_amount' => $refundedAmount],
                $reservation,
            );
        }
    }
}
