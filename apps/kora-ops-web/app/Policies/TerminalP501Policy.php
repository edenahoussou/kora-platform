<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Policies\Concerns\AuthorizesPermissions;

class TerminalP501Policy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'terminals.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'terminals.manage');
    }

    public function update(User $user, TerminalP501 $terminalP501): bool
    {
        return $this->allows($user, 'terminals.manage');
    }
}
