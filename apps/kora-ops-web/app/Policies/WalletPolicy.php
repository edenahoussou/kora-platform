<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Policies\Concerns\AuthorizesPermissions;

class WalletPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'wallet.ops.view');
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $this->allows($user, 'wallet.ops.view');
    }

    public function lookup(User $user): bool
    {
        return $this->allows($user, 'wallet.debit-counter');
    }

    public function debit(User $user, Wallet $wallet): bool
    {
        return $this->allows($user, 'wallet.debit-counter');
    }

    public function adjust(User $user, Wallet $wallet): bool
    {
        return $this->allows($user, 'wallet.adjust');
    }
}
