<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Policies\Concerns\AuthorizesPermissions;

class WalletRestitutionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function viewQueue(User $user): bool
    {
        return $this->manage($user);
    }

    public function viewOwn(User $user): bool
    {
        return $this->allows($user, 'wallet.debit-counter');
    }

    public function view(User $user, WalletRestitution $restitution): bool
    {
        return $this->manage($user)
            || ($this->allows($user, 'wallet.debit-counter') && $restitution->requested_by_user_id === $user->getKey());
    }

    public function request(User $user): bool
    {
        return $this->allows($user, 'wallet.debit-counter');
    }

    public function manage(User $user): bool
    {
        return $this->allows($user, 'wallet.manage-restitutions');
    }

    public function approve(User $user, WalletRestitution $restitution): bool
    {
        return $this->manage($user) && $restitution->status === RestitutionStatus::Requested;
    }

    public function reject(User $user, WalletRestitution $restitution): bool
    {
        return $this->approve($user, $restitution);
    }

    public function markPaid(User $user, WalletRestitution $restitution): bool
    {
        return $this->manage($user) && $restitution->status === RestitutionStatus::Approved;
    }
}
