<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Validation\ValidationException;

final class UpdateWalletLoyaltyRuleDraftAction
{
    /** @param array<string, mixed> $rules */
    public function execute(WalletLoyaltyRuleVersion $version, array $rules): WalletLoyaltyRuleVersion
    {
        if ($version->status !== WalletLoyaltyRuleVersionStatus::Draft) {
            throw ValidationException::withMessages(['version' => 'Seul un brouillon peut être modifié.']);
        }

        $version->update(['rules' => WalletLoyaltyRuleSet::fromArray($rules)]);

        return $version->refresh();
    }
}
