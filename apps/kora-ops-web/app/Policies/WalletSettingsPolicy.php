<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

class WalletSettingsPolicy
{
    use AuthorizesPermissions;

    public function view(User $user): bool
    {
        return $this->allows($user, 'settings.operations.view');
    }

    public function update(User $user): bool
    {
        return $this->allows($user, 'settings.operations.manage');
    }
}
