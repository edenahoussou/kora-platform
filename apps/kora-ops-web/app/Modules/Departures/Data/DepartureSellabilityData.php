<?php

namespace App\Modules\Departures\Data;

use Carbon\CarbonImmutable;

readonly class DepartureSellabilityData
{
    public function __construct(
        public bool $isSellable,
        public SellabilityReason $reason,
        public CarbonImmutable $saleClosesAt,
        public CarbonImmutable $scheduledDepartureAt,
        public CarbonImmutable $effectiveDepartureAt,
        public int $effectiveCutoffMinutes,
        public int $delayMinutes,
    ) {}
}
