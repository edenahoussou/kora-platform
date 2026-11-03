<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

final class ApproveRestitutionAction
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function handle(WalletRestitution $restitution, User $approver): WalletRestitution
    {
        if ($restitution->status !== RestitutionStatus::Requested) {
            throw ValidationException::withMessages(['status' => 'Cette restitution n’est plus en attente d’approbation.']);
        }

        if ((int) $restitution->amount > $this->ledger->availableBalance($restitution->wallet, WalletBalanceType::Cash)) {
            throw ValidationException::withMessages([
                'amount' => 'Le solde Wallet disponible ne couvre plus cette restitution.',
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
