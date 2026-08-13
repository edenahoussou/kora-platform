<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\DynamicPricing\Models\Fare;
use App\Policies\Concerns\AuthorizesPermissions;

class FarePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function view(User $user, Fare $fare): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'pricing.manage');
    }

    public function update(User $user, Fare $fare): bool
    {
        return $this->allows($user, 'pricing.manage');
    }
}
