<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Policies\Concerns\AuthorizesPermissions;

class WalletTransactionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'wallet.ops.view');
    }

    public function view(User $user, WalletTransaction $transaction): bool
    {
        return $this->allows($user, 'wallet.ops.view');
    }
}
