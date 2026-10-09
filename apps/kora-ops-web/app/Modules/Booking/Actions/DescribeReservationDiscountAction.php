<?php

namespace App\Modules\Booking\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\DynamicPricing\Models\Promotion;

class DescribeReservationDiscountAction
{
    /**
     * Human-readable reasons for the discounts applied to a reservation,
     * e.g. "Code promo ABC123 · Remise groupe".
     */
    public function execute(Reservation $reservation): ?string
    {
        $reasons = [];

        if ((int) $reservation->promotion_discount_amount > 0) {
            $promoCode = $reservation->total_snapshot['promotion']['code']
                ?? Promotion::query()->whereKey($reservation->promotion_id)->value('code');
            $reasons[] = 'Code promo '.(string) $promoCode;
        }

        if ((int) ($reservation->total_snapshot['group_discount']['amount'] ?? 0) > 0) {
            $reasons[] = 'Remise groupe';
        }

        if ((int) ($reservation->total_snapshot['company_discount']['amount'] ?? 0) > 0) {
            $reasons[] = 'Remise entreprise';
        }

        return $reasons === [] ? null : implode(' · ', $reasons);
    }

    /** Pre-discount reservation total, matching the invoice payload logic. */
    public function originalTotal(Reservation $reservation): int
    {
        return (int) ($reservation->total_snapshot['subtotal_amount']
            ?? (
                (int) $reservation->total_amount
                + (int) $reservation->promotion_discount_amount
                + (int) ($reservation->total_snapshot['group_discount']['amount'] ?? 0)
                + (int) ($reservation->total_snapshot['company_discount']['amount'] ?? 0)
            ));
    }
}
