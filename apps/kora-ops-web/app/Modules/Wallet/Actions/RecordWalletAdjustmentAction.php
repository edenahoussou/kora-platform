<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Écriture corrective auditée (crédit ou débit), motif obligatoire, jamais de suppression.
 */
final class RecordWalletAdjustmentAction
{
    /**
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     */
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Écrit un ajustement audité avec motif obligatoire.
     *
     * @param  Wallet  $wallet  Wallet corrigé
     * @param  WalletBalanceType  $type  Solde concerné
     * @param  string  $direction  Sens de l'écriture (credit, debit)
     * @param  int  $amount  Montant de la correction
     * @param  string  $reason  Motif de la correction
     * @param  User  $actor  Auteur habilité
     * @return WalletTransaction Écriture créée
     *
     * @throws ValidationException
     */
    public function handle(
        Wallet $wallet,
        WalletBalanceType $type,
        string $direction,
        int $amount,
        string $reason,
        User $actor,
    ): WalletTransaction {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Le motif de l’ajustement est obligatoire.',
            ]);
        }

        $meta = ['adjustment_reason' => $reason, 'actor_uuid' => $actor->uuid];

        $transaction = $direction === 'credit'
            ? $this->ledger->credit($wallet, $type, $amount, WalletTransactionReason::Adjustment, 'adjustment:'.$wallet->uuid.':'.(string) Str::uuid(), $meta, null, $actor)
            : $this->ledger->debit($wallet, $type, $amount, WalletTransactionReason::Adjustment, 'adjustment:'.$wallet->uuid.':'.(string) Str::uuid(), $meta, null, $actor);

        activity('wallet')
            ->performedOn($wallet)
            ->causedBy($actor)
            ->event('wallet.adjusted')
            ->withProperties([
                'balance_type' => $type->value,
                'direction' => $direction,
                'amount' => $amount,
                'reason' => $reason,
            ])
            ->log('wallet.adjusted');

        return $transaction;
    }
}
