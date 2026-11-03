<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réserve des fonds Wallet pour un paiement (hold idempotent, borné par le disponible).
 */
final class PlaceWalletHoldAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly WalletSettings $settings,
    ) {}

    public function handle(
        Wallet $wallet,
        int $cashAmount,
        int $pointsAmount,
        string $idempotencyKey,
        ?Reservation $reservation = null,
        ?Payment $payment = null,
    ): ?WalletHold {
        $cashAmount = max(0, $cashAmount);
        $pointsAmount = max(0, $pointsAmount);

        if ($cashAmount === 0 && $pointsAmount === 0) {
            return null;
        }

        if ($cashAmount > 0 && ! $this->settings->walletCashEnabled) {
            throw ValidationException::withMessages([
                'wallet_amount' => 'Le solde Wallet n’est pas encore ouvert aux paiements.',
            ]);
        }

        if ($pointsAmount > 0 && ! $this->settings->walletPointsEnabled) {
            throw ValidationException::withMessages([
                'points_amount' => 'L’utilisation des points est désactivée.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $cashAmount, $pointsAmount, $idempotencyKey, $reservation, $payment): WalletHold {
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $existing = WalletHold::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($cashAmount > $this->ledger->availableBalance($locked, WalletBalanceType::Cash)) {
                throw ValidationException::withMessages([
                    'wallet_amount' => 'Le solde Wallet disponible est insuffisant.',
                ]);
            }

            if ($pointsAmount > $this->ledger->availableBalance($locked, WalletBalanceType::Points)) {
                throw ValidationException::withMessages([
                    'points_amount' => 'Le solde de points disponible est insuffisant.',
                ]);
            }

            return WalletHold::query()->create([
                'wallet_id' => $locked->getKey(),
                'cash_amount' => $cashAmount,
                'points_amount' => $pointsAmount,
                'reservation_id' => $reservation?->getKey(),
                'payment_id' => $payment?->getKey(),
                'status' => WalletHoldStatus::Active,
                'expires_at' => now()->addMinutes($this->settings->walletHoldTtlMinutes),
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
