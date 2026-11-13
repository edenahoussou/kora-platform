<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\WalletHold;

/**
 * Libère les holds Wallet dont la durée de vie est dépassée.
 */
final class ReleaseExpiredWalletHoldsAction
{
    /**
     * @param  ReleaseWalletHoldAction  $releaseHold  Libération d'un hold
     */
    public function __construct(private readonly ReleaseWalletHoldAction $releaseHold) {}

    /**
     * Libère les holds actifs dont l'échéance est dépassée.
     *
     * @return int Nombre de holds libérés
     */
    public function handle(): int
    {
        $holds = WalletHold::query()
            ->with(['reservation', 'payment'])
            ->where('status', WalletHoldStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->get();

        $releasedCount = 0;

        foreach ($holds as $hold) {
            // Si le hold est rattaché à une réservation encore active (Held et non expirée)
            // avec un paiement encore en cours d'attente opérateur, on protège le hold.
            $reservation = $hold->reservation;
            $payment = $hold->payment;

            if ($reservation !== null
                && $reservation->status->value === 'held'
                && ($reservation->expires_at === null || $reservation->expires_at->isFuture())
                && $payment !== null
                && $payment->status->value === 'pending') {
                continue;
            }

            $this->releaseHold->handle($hold);
            $releasedCount++;
        }

        return $releasedCount;
    }
}
