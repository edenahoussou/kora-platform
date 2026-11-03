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
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function handle(Wallet $wallet, int $amount, User $requester, ?string $note = null): WalletRestitution
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant de la restitution doit être positif.']);
        }

        if ($amount > $this->ledger->availableBalance($wallet, WalletBalanceType::Cash)) {
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
}
