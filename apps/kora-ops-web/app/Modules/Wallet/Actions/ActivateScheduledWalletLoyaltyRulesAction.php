<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

final class ActivateScheduledWalletLoyaltyRulesAction
{
    public function __construct(private readonly SettingsCacheFactory $settingsCache) {}

    public function execute(?Carbon $at = null): int
    {
        $at ??= now();

        $activated = DB::transaction(function () use ($at): int {
            WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            $version = WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Scheduled->value)
                ->where('effective_at', '<=', $at)
                ->orderBy('effective_at')
                ->lockForUpdate()
                ->first();

            if ($version === null) {
                return 0;
            }

            $this->settingsCache->build(WalletSettings::repository())->clear();
            $settings = app(WalletSettings::class);
            $before = WalletLoyaltyRuleSet::fromSettings($settings);

            $settings->fill($version->rules)->save();

            WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                ->lockForUpdate()
                ->update(['status' => WalletLoyaltyRuleVersionStatus::Superseded->value]);

            $version->update([
                'status' => WalletLoyaltyRuleVersionStatus::Active,
                'activated_at' => $at,
            ]);

            activity('settings')
                ->performedOn($version)
                ->causedBy($version->scheduledBy)
                ->event('wallet.loyalty-rules.activated')
                ->withProperties([
                    'group' => 'wallet',
                    'version_uuid' => $version->uuid,
                    'version' => $version->version,
                    'fields' => array_keys($version->rules),
                    'before' => $before,
                    'after' => $version->rules,
                    'effective_at' => $version->effective_at?->toIso8601String(),
                ])
                ->log('wallet.loyalty-rules.activated');

            return 1;
        });

        if ($activated > 0) {
            $this->settingsCache->build(WalletSettings::repository())->clear();
        }

        return $activated;
    }
}
