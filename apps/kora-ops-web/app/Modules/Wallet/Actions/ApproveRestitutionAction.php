<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

/**
 * Valide une demande de restitution après contrôle du solde disponible.
 */
final class ApproveRestitutionAction
{
    /**
     * @param  WalletLedgerService  $ledger  Calcul du disponible
     */
    public function __construct(private readonly WalletLedgerService $ledger) {}

    /**
     * Passe la restitution en « approuvée ».
     *
     * @param  WalletRestitution  $restitution  Demande à approuver
     * @param  User  $approver  Responsable habilité
     * @return WalletRestitution Restitution approuvée
     *
     * @throws ValidationException
     */
    public function handle(WalletRestitution $restitution, User $approver): WalletRestitution
    {
        if ($restitution->status !== RestitutionStatus::Requested) {
            throw ValidationException::withMessages(['status' => 'Cette restitution n’est plus en attente d’approbation.']);
        }

        if (WalletRestitution::reservedAmount($restitution->wallet)
            > $this->ledger->availableBalance($restitution->wallet, WalletBalanceType::Cash)) {
            throw ValidationException::withMessages([
                'amount' => 'Le solde Wallet disponible ne couvre plus les restitutions en attente.',
            ]);
        }

        $restitution->update([
            'status' => RestitutionStatus::Approved,
            'approved_by_user_id' => $approver->getKey(),
            'approved_at' => now(),
        ]);

        activity('wallet')
            ->performedOn($restitution)
            ->causedBy($approver)
            ->event('wallet.restitution.approved')
            ->withProperties(['amount' => (int) $restitution->amount])
            ->log('wallet.restitution.approved');

        return $restitution->fresh();
    }
}
