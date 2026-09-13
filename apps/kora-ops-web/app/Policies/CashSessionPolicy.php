<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Cash\Models\CashSession;
use App\Policies\Concerns\AuthorizesPermissions;

class CashSessionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $user->agency_id !== null && $this->allows($user, 'cash.view');
    }

    public function create(User $user): bool
    {
        return $user->agency_id !== null && $this->allows($user, 'cash.open');
    }

    public function close(User $user, CashSession $cashSession): bool
    {
        return $user->agency_id !== null
            && $user->agency_id === $cashSession->agency_id
            && $user->getKey() === $cashSession->agent_id
            && $this->allows($user, 'cash.close');
    }

    public function approveVariance(User $user, CashSession $cashSession): bool
    {
        return $user->agency_id !== null
            && $this->allows($user, 'cash.approve-variance')
            && $user->agency_id === $cashSession->agency_id;
    }
}
