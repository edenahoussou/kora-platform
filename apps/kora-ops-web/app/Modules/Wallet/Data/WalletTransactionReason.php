<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Motifs d'écriture du ledger Wallet.
 */
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
    case UnconfirmedBookingSettlement = 'unconfirmed_booking_settlement';

    /**
     * Indique si le motif est un gain de points (soumis à compensation).
     *
     * @return bool Vrai pour un earn ou un bonus
     */
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

    /**
     * Indique si le motif réarme la fenêtre d'expiration des points.
     *
     * @return bool Vrai pour un achat, une recharge ou un remboursement de points
     */
    public function isQualifyingActivity(): bool
    {
        return in_array($this, [self::Purchase, self::TopUp, self::RefundPoints], true);
    }
}
