<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transforme un hold actif en débit définitif (une seule fois par hold).
 */
final class CaptureWalletHoldAction
{
    /**
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     */
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Transforme le hold en débit définitif, une seule fois.
     *
     * @param  WalletHold  $hold  Hold actif
     * @param  Payment|null  $payment  Paiement déclencheur
     * @param  User|null  $actor  Agent à l'origine du débit
     * @param  int|null  $agencyId  Agence de rattachement
     * @return WalletHold Hold capturé
     *
     * @throws ValidationException
     */
    public function handle(WalletHold $hold, ?Payment $payment = null, ?User $actor = null, ?int $agencyId = null): WalletHold
    {
        return DB::transaction(function () use ($hold, $payment, $actor, $agencyId): WalletHold {
            $locked = WalletHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === WalletHoldStatus::Captured) {
                return $locked;
            }

            if ($locked->status === WalletHoldStatus::Released) {
                throw ValidationException::withMessages([
                    'wallet' => 'Ce hold Wallet a déjà été libéré.',
                ]);
            }

            $locked->update([
                'status' => WalletHoldStatus::Captured,
                'payment_id' => $payment?->getKey() ?? $locked->payment_id,
            ]);

            $wallet = $locked->wallet;
            $meta = ['hold_uuid' => $locked->uuid];
            if ($actor !== null) {
                $meta['actor_uuid'] = $actor->uuid;
            }

            if ($locked->cash_amount > 0) {
                $this->ledger->debit(
                    $wallet,
                    WalletBalanceType::Cash,
                    $locked->cash_amount,
                    WalletTransactionReason::Purchase,
                    'hold:'.$locked->uuid.':cash',
                    $meta,
                    $payment ?? $locked->reservation,
                    $actor,
                    $agencyId,
                );
            }

            if ($locked->points_amount > 0) {
                $this->ledger->debit(
                    $wallet,
                    WalletBalanceType::Points,
                    $locked->points_amount,
                    WalletTransactionReason::Purchase,
                    'hold:'.$locked->uuid.':points',
                    $meta,
                    $payment ?? $locked->reservation,
                    $actor,
                    $agencyId,
                );
            }

            return $locked->fresh();
        });
    }
}
