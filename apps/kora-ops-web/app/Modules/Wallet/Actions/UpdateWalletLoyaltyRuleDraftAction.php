<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateWalletLoyaltyRuleDraftAction
{
    /** @param array<string, mixed> $rules */
    public function execute(WalletLoyaltyRuleVersion $version, array $rules): WalletLoyaltyRuleVersion
    {
        return DB::transaction(function () use ($version, $rules): WalletLoyaltyRuleVersion {
            $locked = WalletLoyaltyRuleVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== WalletLoyaltyRuleVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'Seul un brouillon peut être modifié.']);
            }

            $locked->update(['rules' => WalletLoyaltyRuleSet::fromArray($rules)]);

            return $locked->refresh();
        });
    }
}
