<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletTransactionReason: string
{
    use HasEnumUtilities;

    case TopUp = 'topup';
    case Purchase = 'purchase';
    case RefundCash = 'refund_cash';
    case RefundPoints = 'refund_points';
    case PointsEarn = 'points_earn';
    case BonusWelcome = 'bonus_welcome';
    case BonusReferral = 'bonus_referral';
    case BonusAnniversary = 'bonus_anniversary';
    case BonusUsage = 'bonus_usage';
    case PointsExpire = 'points_expire';
    case PointsCorrection = 'points_correction';
    case CompensationOffset = 'compensation_offset';
    case Restitution = 'restitution';
    case Adjustment = 'adjustment';

    public function isLoyaltyEarn(): bool
    {
        return in_array($this, [
            self::PointsEarn,
            self::BonusWelcome,
            self::BonusReferral,
            self::BonusAnniversary,
            self::BonusUsage,
        ], true);
    }

    public function isQualifyingActivity(): bool
    {
        return in_array($this, [self::Purchase, self::TopUp, self::RefundPoints], true);
    }
}
