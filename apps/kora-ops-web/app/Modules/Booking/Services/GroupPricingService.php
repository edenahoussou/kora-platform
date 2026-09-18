<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\DynamicPricing\Data\GroupDiscountRuleStatus;
use App\Modules\DynamicPricing\Models\GroupDiscountRule;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class GroupPricingService
{
    /**
     * @param  list<int>  $unitAmounts
     * @param  list<int>  $dynamicAdjustments
     * @param  int  $promotionDiscount  Remise promotion appliquée avant la
     *                                  remise groupe : le pourcentage de remise groupe se calcule sur le
     *                                  sous-total diminué de la promotion (ordre tarif → dynamique →
     *                                  promotion → remise groupe).
     * @return array{
     *     subtotal_amount: int,
     *     dynamic_adjustment_amount: int,
     *     group: bool,
     *     group_discount: array{applied: bool, threshold: int|null, percentage: float, amount: int, rule_uuid: string|null},
     *     promotion_discount_amount: int,
     *     total_amount: int,
     *     currency: string,
     *     passenger_allocations: list<int>,
     *     calculated_at: string,
     *     expires_at: string
     * }
     *
     * @throws ValidationException
     */
    public function calculate(
        array $unitAmounts,
        int $passengerCount,
        string $currency,
        array $dynamicAdjustments = [],
        ?CarbonInterface $expiresAt = null,
        int $promotionDiscount = 0,
    ): array {
        if ($unitAmounts === [] || $passengerCount < 1) {
            throw ValidationException::withMessages(['passenger_count' => 'Le devis doit contenir au moins un passager et une jambe.']);
        }

        $settings = GroupBookingSetting::current();
        if ($passengerCount > $settings->maximum_passengers) {
            throw ValidationException::withMessages([
                'passenger_count' => 'Le nombre de passagers dépasse le maximum configuré pour une transaction.',
            ]);
        }

        $subtotal = array_sum($unitAmounts) * $passengerCount;
        $dynamicAdjustment = array_sum($dynamicAdjustments) * $passengerCount;
        $group = $passengerCount >= $settings->minimum_passengers;
        $rule = GroupDiscountRule::query()
            ->where('status', GroupDiscountRuleStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('starts_at')
            ->first();
        $threshold = $rule?->passenger_threshold;
        $percentage = $rule ? (float) $rule->percentage : 0.0;
        $discountApplied = $rule !== null && $group && $threshold !== null && $passengerCount >= $threshold;
        $promotionDiscount = min(max($promotionDiscount, 0), $subtotal);
        $discountAmount = $discountApplied ? (int) round(($subtotal - $promotionDiscount) * $percentage / 100) : 0;
        $total = $subtotal - $promotionDiscount - $discountAmount;
        $expiration = $expiresAt ?? now()->addMinutes((int) config('kora.bookings.quote_ttl_minutes', 10));

        return [
            'subtotal_amount' => $subtotal,
            'dynamic_adjustment_amount' => $dynamicAdjustment,
            'group' => $group,
            'group_discount' => [
                'applied' => $discountApplied,
                'threshold' => $threshold,
                'percentage' => $percentage,
                'amount' => $discountAmount,
                'rule_uuid' => $rule?->uuid,
            ],
            'promotion_discount_amount' => $promotionDiscount,
            'total_amount' => $total,
            'currency' => $currency,
            'passenger_allocations' => $this->allocate($total, $passengerCount),
            'calculated_at' => now()->toIso8601String(),
            'expires_at' => $expiration->toIso8601String(),
        ];
    }

    /** @return list<int> */
    private function allocate(int $total, int $passengerCount): array
    {
        $unit = intdiv($total, $passengerCount);
        $remainder = $total % $passengerCount;

        return array_map(
            fn (int $index): int => $unit + ($index < $remainder ? 1 : 0),
            range(0, $passengerCount - 1),
        );
    }
}
