<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

/**
 * Ouvre une demande de restitution exceptionnelle du solde cash.
 */
final class RequestRestitutionAction
{
    /**
     * @param  WalletLedgerService  $ledger  Calcul du disponible
     */
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Ouvre une demande de restitution contrôlée sur le disponible.
     *
     * @param  Wallet  $wallet  Wallet concerné
     * @param  int  $amount  Montant demandé
     * @param  User  $requester  Auteur de la demande
     * @param  string|null  $note  Motif ou précision
     * @return WalletRestitution Demande créée
     *
     * @throws ValidationException
     */
    public function handle(Wallet $wallet, int $amount, User $requester, ?string $note = null): WalletRestitution
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant de la restitution doit être positif.']);
        }

        if ($amount > $this->availableForRestitution($wallet)) {
            throw ValidationException::withMessages([
                'amount' => 'Le solde Wallet disponible est insuffisant pour cette restitution.',
            ]);
        }

        return WalletRestitution::query()->create([
            'wallet_id' => $wallet->getKey(),
            'amount' => $amount,
            'status' => RestitutionStatus::Requested,
            'requested_by_user_id' => $requester->getKey(),
            'note' => $note,
        ]);
    }

    /**
     * Disponible cash diminué des restitutions déjà demandées ou approuvées.
     */
    private function availableForRestitution(Wallet $wallet): int
    {
        return $this->ledger->availableBalance($wallet, WalletBalanceType::Cash)
            - WalletRestitution::reservedAmount($wallet);
    }
}
