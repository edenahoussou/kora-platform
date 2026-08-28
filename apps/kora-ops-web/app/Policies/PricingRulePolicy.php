<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Policies\Concerns\AuthorizesPermissions;

class PricingRulePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function view(User $user, PricingRule $rule): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'pricing.manage');
    }

    public function update(User $user, PricingRule $rule): bool
    {
        return $this->allows($user, 'pricing.manage');
    }
}
