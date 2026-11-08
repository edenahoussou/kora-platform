<?php

namespace Database\Seeders;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Database\Seeder;

class WalletLoyaltyRuleVersionSeeder extends Seeder
{
    public function run(): void
    {
        if (WalletLoyaltyRuleVersion::query()->exists()) {
            return;
        }

        $settings = app(WalletSettings::class);

        WalletLoyaltyRuleVersion::query()->create([
            'version' => 1,
            'status' => WalletLoyaltyRuleVersionStatus::Active,
            'rules' => WalletLoyaltyRuleSet::fromSettings($settings),
            'published_at' => now(),
            'activated_at' => now(),
        ]);
    }
}
