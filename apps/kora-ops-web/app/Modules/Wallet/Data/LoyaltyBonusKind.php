<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Types de bonus fidélité accordés par le Wallet.
 */
enum LoyaltyBonusKind: string
{
    use HasEnumUtilities;

    case Welcome = 'welcome';
    case Referral = 'referral';
    case Anniversary = 'anniversary';
    case Usage = 'usage';

    /**
     * Motif d'écriture associé au bonus.
     *
     * @return WalletTransactionReason Motif du ledger
     */
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
