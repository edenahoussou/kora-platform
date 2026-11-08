<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use App\Policies\Concerns\AuthorizesPermissions;

class WalletLoyaltyRuleVersionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'wallet.loyalty-rules.view');
    }

    public function view(User $user, WalletLoyaltyRuleVersion $version): bool
    {
        return $this->allows($user, 'wallet.loyalty-rules.view');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, WalletLoyaltyRuleVersion $version): bool
    {
        return $this->manage($user) && $version->status === WalletLoyaltyRuleVersionStatus::Draft;
    }

    public function simulate(User $user, WalletLoyaltyRuleVersion $version): bool
    {
        return $this->update($user, $version);
    }

    public function schedule(User $user, WalletLoyaltyRuleVersion $version): bool
    {
        return $this->update($user, $version);
    }

    public function manage(User $user): bool
    {
        return $this->allows($user, 'wallet.loyalty-rules.manage');
    }
}
