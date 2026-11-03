<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use Illuminate\Support\Facades\DB;

/**
 * Débit Wallet atomique au guichet : hold puis capture immédiate, tracé agent/agence.
 */
final class DebitWalletForSaleAction
{
    /**
     * @param  PlaceWalletHoldAction  $placeHold  Réservation de fonds
     * @param  CaptureWalletHoldAction  $captureHold  Capture du hold
     */
    public function __construct(
        private readonly PlaceWalletHoldAction $placeHold,
        private readonly CaptureWalletHoldAction $captureHold,
    ) {}

    /**
     * Réserve puis débite immédiatement les fonds pour une vente guichet.
     *
     * @param  Wallet  $wallet  Wallet du titulaire
     * @param  int  $cashAmount  Montant débité du solde cash
     * @param  int  $pointsAmount  Points utilisés
     * @param  string  $idempotencyKey  Clé d'idempotence de l'opération
     * @param  Reservation  $reservation  Réservation payée
     * @param  Payment  $payment  Paiement guichet créé
     * @param  User  $agent  Agent au guichet
     * @return WalletHold|null Hold capturé, null si aucun montant Wallet
     */
    public function handle(
        Wallet $wallet,
        int $cashAmount,
        int $pointsAmount,
        string $idempotencyKey,
        Reservation $reservation,
        Payment $payment,
        User $agent,
    ): ?WalletHold {
        return DB::transaction(function () use ($wallet, $cashAmount, $pointsAmount, $idempotencyKey, $reservation, $payment, $agent): ?WalletHold {
            $hold = $this->placeHold->handle(
                wallet: $wallet,
                cashAmount: $cashAmount,
                pointsAmount: $pointsAmount,
                idempotencyKey: $idempotencyKey,
                reservation: $reservation,
                payment: $payment,
            );

            if ($hold === null) {
                return null;
            }

            return $this->captureHold->handle($hold, $payment, $agent, $agent->agency_id);
        });
    }
}
