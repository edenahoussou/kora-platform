<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Policies\Concerns\AuthorizesPermissions;

class TaxRulePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function view(User $user, TaxRule $rule): bool
    {
        return $this->allows($user, 'pricing.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'pricing.manage');
    }

    public function update(User $user, TaxRule $rule): bool
    {
        return $this->allows($user, 'pricing.manage');
    }
}
