<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;

/**
 * Reprend les points indus après un remboursement, au prorata du montant rendu.
 */
final class ApplyLoyaltyCorrectionAction
{
    /**
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     */
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Reprend les points gagnés au prorata du montant remboursé.
     *
     * @param  Reservation  $reservation  Réservation remboursée
     * @param  int|null  $refundedAmount  Montant rendu, null pour un remboursement total
     * @param  ReservationPassenger|null  $passenger  Voyageur concerné par un remboursement individuel
     */
    public function handle(
        Reservation $reservation,
        ?int $refundedAmount = null,
        ?ReservationPassenger $passenger = null,
    ): void {
        if ($passenger !== null) {
            $allocations = $reservation->total_snapshot['passenger_allocations'] ?? [];
            $passengerIndex = (int) ($passenger->passenger_index ?? 0);
            $total = (int) ($allocations[$passengerIndex] ?? 0);
        } else {
            $total = (int) $reservation->total_amount;
        }

        $ratio = $refundedAmount === null || $total <= 0 ? 1.0 : min(1.0, $refundedAmount / $total);

        if ($ratio <= 0) {
            return;
        }

        $earned = WalletTransaction::query()
            ->where('reference_type', $reservation->getMorphClass())
            ->where('reference_id', $reservation->getKey())
            ->where('reason', WalletTransactionReason::PointsEarn->value)
            ->when($passenger !== null, fn ($query) => $query->whereJsonContains('meta->passenger_uuid', $passenger->uuid))
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
                'loyalty:correction:'.$reservation->uuid.':'.($passenger === null ? 'reservation' : $passenger->uuid).':'.($refundedAmount ?? 'full'),
                [
                    'reservation_uuid' => $reservation->uuid,
                    'passenger_uuid' => $passenger?->uuid,
                    'refunded_amount' => $refundedAmount,
                ],
                $reservation,
            );
        }
    }
}
