<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\CompanyAccounts\Data\CompanyDiscountType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Settings\Settings\OperationsSettings;
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
     *     group_discount: array{applied: bool, threshold: int, percentage: float, amount: int, rule_uuid: null},
     *     company_discount?: array{applied: bool, type: string|null, value: float|null, amount: int},
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
        ?CompanyAccount $companyAccount = null,
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
        $operations = app(OperationsSettings::class);
        $threshold = $operations->groupDiscountPassengerThreshold;
        $percentage = $operations->groupDiscountPercentage;
        $discountApplied = $companyAccount === null
            && $operations->groupDiscountEnabled
            && $group
            && $passengerCount >= $threshold;
        $promotionDiscount = min(max($promotionDiscount, 0), $subtotal);
        $discountBase = $subtotal - $promotionDiscount;
        $discountAmount = $discountApplied ? (int) round($discountBase * $percentage / 100) : 0;
        $companyDiscount = $companyAccount !== null
            ? $this->companyDiscount($companyAccount, $discountBase)
            : null;
        $total = $discountBase - $discountAmount - ($companyDiscount['amount'] ?? 0);
        $expiration = $expiresAt ?? now()->addMinutes($operations->quoteTtlMinutes);

        $pricing = [
            'subtotal_amount' => $subtotal,
            'dynamic_adjustment_amount' => $dynamicAdjustment,
            'group' => $group,
            'group_discount' => [
                'applied' => $discountApplied,
                'threshold' => $threshold,
                'percentage' => $percentage,
                'amount' => $discountAmount,
                'rule_uuid' => null,
            ],
            'promotion_discount_amount' => $promotionDiscount,
            'total_amount' => $total,
            'currency' => $currency,
            'passenger_allocations' => $this->allocate($total, $passengerCount),
            'calculated_at' => now()->toIso8601String(),
            'expires_at' => $expiration->toIso8601String(),
        ];

        if ($companyDiscount !== null) {
            $pricing['company_discount'] = $companyDiscount;
        }

        return $pricing;
    }

    /** @return array{applied: bool, type: string|null, value: float|null, amount: int} */
    private function companyDiscount(CompanyAccount $companyAccount, int $baseAmount): array
    {
        $type = $companyAccount->discount_type;
        $value = $companyAccount->discount_value !== null
            ? (float) $companyAccount->discount_value
            : null;

        if ($type === null || $value === null || $value <= 0) {
            return ['applied' => false, 'type' => $type?->value, 'value' => $value, 'amount' => 0];
        }

        $amount = $type === CompanyDiscountType::Percentage
            ? (int) round($baseAmount * $value / 100)
            : min((int) round($value), $baseAmount);

        return ['applied' => $amount > 0, 'type' => $type->value, 'value' => $value, 'amount' => $amount];
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
