<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Settings\Settings\WalletSettings;

class WalletLoyaltyRuleSet
{
    public const FIELDS = [
        'loyaltyEarnRateBasisPoints',
        'pointValueFcfa',
        'pointsMinSpend',
        'bonusWelcomePoints',
        'bonusReferralPoints',
        'bonusAnniversaryPoints',
        'bonusUsageNth',
        'bonusUsagePoints',
        'pointsExpiryMonths',
        'pointsExpiryReminderDays',
    ];

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function fromArray(array $values): array
    {
        return array_intersect_key($values, array_flip(self::FIELDS));
    }

    /** @return array<string, mixed> */
    public static function fromSettings(WalletSettings $settings): array
    {
        return self::fromArray($settings->toArray());
    }
}
