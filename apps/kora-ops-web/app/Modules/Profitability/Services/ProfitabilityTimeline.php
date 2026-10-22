<?php

namespace App\Modules\Profitability\Services;

use App\Modules\Finance\Data\RevenuePeriod;
use Carbon\CarbonImmutable;

/**
 * Série temporelle de la période : recette nette de service (par date de
 * départ) et coûts engagés (par date de dépense), quotidiens et cumulés,
 * avec la recette cumulée de la période précédente alignée par rang.
 */
class ProfitabilityTimeline
{
    public const MIN_POINTS = 3;

    /**
     * @param  array<string, mixed>  $revenue
     * @param  array<string, mixed>  $costs
     * @param  array<string, mixed>  $previousRevenue
     * @return array<string, mixed>
     */
    public function build(RevenuePeriod $period, array $revenue, array $costs, array $previousRevenue): array
    {
        $points = $this->emptyPoints($period);

        foreach ($revenue['byDeparture'] as $departureId => $metric) {
            $meta = $revenue['departureMeta'][$departureId] ?? null;
            if ($meta === null) {
                continue;
            }

            $key = $period->bucketKey(CarbonImmutable::parse($meta['scheduledAt'])->setTimezone(RevenuePeriod::TIMEZONE));
            if (isset($points[$key])) {
                $points[$key]['revenue'] += (int) $metric['net'];
            }
        }

        foreach ($revenue['unattributedByDate'] as $date => $net) {
            $key = $period->bucketKey(CarbonImmutable::parse($date));
            if (isset($points[$key])) {
                $points[$key]['revenue'] += (int) $net;
            }
        }

        foreach ($costs['byDate'] as $date => $amount) {
            $key = $period->bucketKey(CarbonImmutable::parse($date));
            if (isset($points[$key])) {
                $points[$key]['cost'] += (int) $amount;
            }
        }

        $previous = $this->cumulative($this->revenueSeries($period->comparisonPeriod(), $previousRevenue));

        $revenueCum = 0;
        $costCum = 0;
        $breakEvenDay = null;
        $index = 0;

        foreach ($points as &$point) {
            $revenueCum += $point['revenue'];
            $costCum += $point['cost'];
            $point['revenueCum'] = $revenueCum;
            $point['costCum'] = $costCum;
            $point['prevRevenueCum'] = $previous[$index] ?? null;

            if ($breakEvenDay === null && $revenueCum > 0 && $revenueCum >= $costCum) {
                $breakEvenDay = $point['label'];
            }

            $index++;
        }
        unset($point);

        return [
            'bucket' => $period->bucket(),
            'points' => count($points) < self::MIN_POINTS ? [] : array_values($points),
            'breakEvenDay' => count($points) < self::MIN_POINTS ? null : $breakEvenDay,
        ];
    }

    /**
     * Recette nette par bucket (départs + non rattachés), non cumulée.
     *
     * @param  array<string, mixed>  $revenue
     * @return list<int>
     */
    private function revenueSeries(RevenuePeriod $period, array $revenue): array
    {
        $series = [];

        foreach ($revenue['byDeparture'] as $departureId => $metric) {
            $meta = $revenue['departureMeta'][$departureId] ?? null;
            if ($meta === null) {
                continue;
            }

            $key = $period->bucketKey(CarbonImmutable::parse($meta['scheduledAt'])->setTimezone(RevenuePeriod::TIMEZONE));
            $series[$key] = ($series[$key] ?? 0) + (int) $metric['net'];
        }

        foreach ($revenue['unattributedByDate'] as $date => $net) {
            $key = $period->bucketKey(CarbonImmutable::parse($date));
            $series[$key] = ($series[$key] ?? 0) + (int) $net;
        }

        $ordered = [];
        $cursor = $period->localStart;
        $step = $period->bucket();

        while ($cursor->lessThanOrEqualTo($period->localEnd)) {
            $ordered[] = $series[$period->bucketKey($cursor)] ?? 0;
            $cursor = match ($step) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow()->startOfMonth(),
                default => $cursor->addDay(),
            };
        }

        return $ordered;
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private function cumulative(array $values): array
    {
        $total = 0;

        return array_map(static function (int $value) use (&$total): int {
            $total += $value;

            return $total;
        }, $values);
    }

    /** @return array<string, array<string, mixed>> */
    private function emptyPoints(RevenuePeriod $period): array
    {
        $points = [];
        $cursor = $period->localStart;
        $step = $period->bucket();

        while ($cursor->lessThanOrEqualTo($period->localEnd)) {
            $key = $period->bucketKey($cursor);
            $bucketEnd = match ($step) {
                'week' => $cursor->endOfWeek()->min($period->localEnd),
                'month' => $cursor->endOfMonth()->min($period->localEnd),
                default => $cursor,
            };
            $label = match ($step) {
                'week' => $cursor->format('d/m').' – '.$bucketEnd->format('d/m'),
                'month' => $cursor->format('m/Y'),
                default => $cursor->format('d/m'),
            };
            $points[$key] = [
                'key' => $key,
                'label' => $label,
                'revenue' => 0,
                'cost' => 0,
                'revenueCum' => 0,
                'costCum' => 0,
                'prevRevenueCum' => null,
            ];
            $cursor = match ($step) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow()->startOfMonth(),
                default => $cursor->addDay(),
            };
        }

        return $points;
    }
}
