<?php

namespace App\Modules\DynamicPricing\Data;

final readonly class PricingBreakdownData
{
    /**
     * @param  array{applied: bool, threshold: int|null, percentage: float, amount: int, rule_uuid: string|null}  $groupDiscount
     * @param  list<array<string, mixed>>  $promotions
     * @param  list<int>  $passengerAllocations
     */
    public function __construct(
        public int $baseFareAmount,
        public int $dynamicAdjustmentAmount,
        public int $adjustedSubtotalAmount,
        public bool $isGroup,
        public array $groupDiscount,
        public array $promotions,
        public int $promotionDiscountAmount,
        public int $totalAmount,
        public string $currency,
        public array $passengerAllocations,
    ) {}

    /** @param array<string, mixed> $pricing */
    public static function fromGroupPricing(array $pricing): self
    {
        return new self(
            baseFareAmount: (int) $pricing['subtotal_amount'] - (int) $pricing['dynamic_adjustment_amount'],
            dynamicAdjustmentAmount: (int) $pricing['dynamic_adjustment_amount'],
            adjustedSubtotalAmount: (int) $pricing['subtotal_amount'],
            isGroup: (bool) $pricing['group'],
            groupDiscount: $pricing['group_discount'],
            promotions: [],
            promotionDiscountAmount: 0,
            totalAmount: (int) $pricing['total_amount'],
            currency: (string) $pricing['currency'],
            passengerAllocations: array_values(array_map('intval', $pricing['passenger_allocations'])),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'base_fare_amount' => $this->baseFareAmount,
            'dynamic_adjustment_amount' => $this->dynamicAdjustmentAmount,
            'adjusted_subtotal_amount' => $this->adjustedSubtotalAmount,
            'group' => $this->isGroup,
            'group_discount' => $this->groupDiscount,
            'promotions' => $this->promotions,
            'promotion_discount_amount' => $this->promotionDiscountAmount,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'passenger_allocations' => $this->passengerAllocations,
        ];
    }
}
