<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWalletLoyaltyRuleDraftAction
{
    public function execute(User $actor): WalletLoyaltyRuleVersion
    {
        return DB::transaction(function () use ($actor): WalletLoyaltyRuleVersion {
            $active = WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($active === null) {
                throw ValidationException::withMessages([
                    'rules' => 'La version initiale de fidélité doit être initialisée depuis les paramètres actifs avant de créer un brouillon.',
                ]);
            }

            $existing = WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Draft->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $lastVersion = (int) WalletLoyaltyRuleVersion::query()->max('version');

            return WalletLoyaltyRuleVersion::query()->create([
                'version' => $lastVersion + 1,
                'status' => WalletLoyaltyRuleVersionStatus::Draft,
                'rules' => $active->rules,
                'created_by_user_id' => $actor->getKey(),
            ]);
        });
    }
}
