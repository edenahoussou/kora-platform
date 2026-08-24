<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Departures\Data\DepartureSellabilityData;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Data\SellabilityReason;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Support\CutoffResolver;
use Carbon\CarbonImmutable;

class ResolveDepartureSellabilityAction
{
    public function __construct(private CutoffResolver $cutoffResolver) {}

    public function handle(Departure $departure, ?CarbonImmutable $now = null): DepartureSellabilityData
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $scheduledDepartureAt = $departure->scheduledDepartureAt();
        $delayMinutes = max(0, (int) $departure->delay_minutes);
        $effectiveDepartureAt = $scheduledDepartureAt->addMinutes($delayMinutes);
        $effectiveCutoffMinutes = $this->cutoffResolver->resolve($departure);
        $saleClosesAt = $effectiveDepartureAt->subMinutes($effectiveCutoffMinutes);
        $status = $departure->status;
        $sellableStatuses = $this->sellableStatuses();
        $statusIsSellable = in_array($status->value, $sellableStatuses, true);
        $beforeSaleClose = $now->lessThan($saleClosesAt);
        $isWithinGrace = $delayMinutes > 0
            && $now->greaterThanOrEqualTo($scheduledDepartureAt->subMinutes($effectiveCutoffMinutes));

        $reason = match ($status) {
            DepartureStatus::Departed => SellabilityReason::Departed,
            DepartureStatus::Arrived => SellabilityReason::Arrived,
            DepartureStatus::Cancelled => SellabilityReason::Cancelled,
            DepartureStatus::Boarding => SellabilityReason::Boarding,
            DepartureStatus::Scheduled => $beforeSaleClose
                ? ($isWithinGrace ? SellabilityReason::WithinGrace : SellabilityReason::BeforeCutoff)
                : SellabilityReason::PastCutoff,
        };

        return new DepartureSellabilityData(
            isSellable: $statusIsSellable && $beforeSaleClose,
            reason: $reason,
            saleClosesAt: $saleClosesAt,
            scheduledDepartureAt: $scheduledDepartureAt,
            effectiveDepartureAt: $effectiveDepartureAt,
            effectiveCutoffMinutes: $effectiveCutoffMinutes,
            delayMinutes: $delayMinutes,
        );
    }

    /** @return list<string> */
    private function sellableStatuses(): array
    {
        $statuses = config('kora.departures.sellability.sellable_statuses', ['scheduled']);

        return is_array($statuses)
            ? array_values(array_filter($statuses, 'is_string'))
            : ['scheduled'];
    }
}
