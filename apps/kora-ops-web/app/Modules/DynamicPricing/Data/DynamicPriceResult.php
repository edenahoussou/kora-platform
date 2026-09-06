<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;

readonly class DynamicPriceResult
{
    public function __construct(
        public Fare $fare,
        public ?PricingRule $rule,
        public float $fillRate,
        public float $hoursBeforeDeparture,
        public float $coefficient,
        public int $baseAmount,
        public int $finalAmount,
    ) {}

    public function adjustmentAmount(): int
    {
        return $this->finalAmount - $this->baseAmount;
    }
}
