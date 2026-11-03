<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Models\WalletRestitution;
use Illuminate\Validation\ValidationException;

final class RejectRestitutionAction
{
    public function handle(WalletRestitution $restitution, User $actor, string $reason): WalletRestitution
    {
        if ($restitution->status->isTerminal()) {
            throw ValidationException::withMessages(['status' => 'Cette restitution est déjà clôturée.']);
        }

        $restitution->update([
            'status' => RestitutionStatus::Rejected,
            'approved_by_user_id' => $actor->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        activity('wallet')
            ->performedOn($restitution)
            ->causedBy($actor)
            ->event('wallet.restitution.rejected')
            ->withProperties(['reason' => $reason])
            ->log('wallet.restitution.rejected');

        return $restitution->fresh();
    }
}
