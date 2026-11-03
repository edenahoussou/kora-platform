<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('wallet', function ($settings): void {
            $settings->add('walletCashEnabled', false);
            $settings->add('walletPointsEnabled', true);
            $settings->add('loyaltyEarnRateBasisPoints', 200);
            $settings->add('pointValueFcfa', 1);
            $settings->add('pointsMinSpend', 100);
            $settings->add('bonusWelcomePoints', 500);
            $settings->add('bonusReferralPoints', 200);
            $settings->add('bonusAnniversaryPoints', 100);
            $settings->add('bonusUsageNth', 5);
            $settings->add('bonusUsagePoints', 50);
            $settings->add('pointsExpiryMonths', 6);
            $settings->add('pointsExpiryReminderDays', [30, 7, 1]);
            $settings->add('walletHoldTtlMinutes', 15);
            $settings->add('topupMinAmount', null);
            $settings->add('topupMaxAmount', null);
            $settings->add('cashBalanceCap', null);
            $settings->add('topupAmountPresets', [2000, 5000, 10000, 20000]);
        });
    }
};
