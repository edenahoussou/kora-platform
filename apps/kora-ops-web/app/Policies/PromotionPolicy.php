<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Policies\Concerns\AuthorizesPermissions;

class PromotionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'pricing.manage');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->allows($user, 'pricing.manage');
    }

    public function manage(User $user): bool
    {
        return $this->allows($user, 'pricing.manage');
    }
}
