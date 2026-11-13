<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Route un remboursement vers le Wallet cash du payeur personnel, au prorata
 * du financement initial (points remboursés en points, argent en cash).
 */
final class RouteRefundToWalletAction
{
    /**
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     */
    public function __construct(
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly WalletLedgerService $ledger,
    ) {}

    /**
     * Répartit le remboursement entre points et cash du payeur personnel.
     *
     * @param  Reservation  $reservation  Réservation remboursée
     * @param  Payment  $payment  Paiement d'origine
     * @param  int  $amount  Montant remboursé
     * @param  string  $reference  Référence du remboursement
     * @return array{wallet: Wallet, points: int, cash: int}|null Répartition, null sans payeur personnel
     */
    public function handle(Reservation $reservation, Payment $payment, int $amount, string $reference): ?array
    {
        if ($amount <= 0 || $reservation->payer_type !== PayerType::Personal) {
            return null;
        }

        $owner = $reservation->payer;

        return DB::transaction(function () use ($reservation, $payment, $owner, $amount, $reference): ?array {
            $payment = Payment::query()->where('id', $payment->getKey())->lockForUpdate()->firstOrFail();
            $wallet = WalletHold::query()->where('payment_id', $payment->getKey())
                ->where('status', WalletHoldStatus::Captured)->first()->wallet ?? null;
            if ($wallet === null) {
                if ($owner === null) {
                    return null;
                }

                $wallet = $this->findOrCreateWallet->handle($owner);
            }
            $completed = $payment->refunds()->where('status', PaymentRefundStatus::Completed)->get();
            $previous = $completed->firstWhere('reference', $reference);
            if ($previous !== null) {
                return [
                    'wallet' => $wallet,
                    'points' => (int) ($previous->metadata['wallet_points'] ?? 0),
                    'cash' => (int) ($previous->metadata['wallet_cash'] ?? 0),
                ];
            }

            $total = max(1, $payment->amount);
            $cumulative = min($total, $amount + (int) $completed->sum('amount'));
            $funding = WalletPurchaseFunding::fromPayment($payment);
            $pointsTarget = intdiv($cumulative * $funding->points, $total);
            // Au taux 1:1, la part cash est le complément exact de la part points ;
            // deux arrondis séparés perdraient 1 FCFA à chaque remboursement partiel.
            $cashTarget = $funding->pointRate === 1
                ? $cumulative - $pointsTarget
                : intdiv($cumulative * max(0, $total - $funding->pointsValue), $total);
            $points = max(0, $pointsTarget - $completed->sum(fn ($refund) => (int) ($refund->metadata['wallet_points'] ?? 0)));
            $cash = max(0, $cashTarget - $completed->sum(fn ($refund) => (int) ($refund->metadata['wallet_cash'] ?? 0)));

            if ($points > 0) {
                $this->ledger->credit(
                    $wallet, WalletBalanceType::Points, $points, WalletTransactionReason::RefundPoints,
                    'refund:'.$reference.':points', ['reservation_uuid' => $reservation->uuid], $reservation,
                );
            }

            if ($cash > 0) {
                $this->ledger->credit(
                    $wallet, WalletBalanceType::Cash, $cash, WalletTransactionReason::RefundCash,
                    'refund:'.$reference.':cash', ['reservation_uuid' => $reservation->uuid], $reservation,
                );
            }

            return ['wallet' => $wallet, 'points' => $points, 'cash' => $cash];
        });
    }
}
