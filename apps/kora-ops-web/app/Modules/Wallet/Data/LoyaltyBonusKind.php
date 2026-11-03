<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum LoyaltyBonusKind: string
{
    use HasEnumUtilities;

    case Welcome = 'welcome';
    case Referral = 'referral';
    case Anniversary = 'anniversary';
    case Usage = 'usage';

    public function reason(): WalletTransactionReason
    {
        return match ($this) {
            self::Welcome => WalletTransactionReason::BonusWelcome,
            self::Referral => WalletTransactionReason::BonusReferral,
            self::Anniversary => WalletTransactionReason::BonusAnniversary,
            self::Usage => WalletTransactionReason::BonusUsage,
        };
    }
}
