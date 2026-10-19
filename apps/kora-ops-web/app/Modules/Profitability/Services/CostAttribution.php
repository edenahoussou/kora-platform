<?php

namespace App\Modules\Profitability\Services;

use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Profitability\Data\CostAllocationRule;
use App\Modules\Profitability\Data\CostBehavior;
use App\Modules\Profitability\Data\CostCategory;
use App\Modules\Profitability\Support\IntegerAllocator;

/**
 * Dépenses approuvées → coûts directs, répartis et non affectés par axe,
 * avec mapping fixe/variable et règle de répartition déterministe.
 */
class CostAttribution
{
    private const CURRENCY = 'XOF';

    /**
     * @param  array<int, array<string, mixed>>  $departureMeta
     * @return array<string, mixed>
     */
    public function build(RevenuePeriod $period, array $departureMeta): array
    {
        $expenses = Expense::query()
            ->with([
                'category:id,code,label',
                'maintenanceDetails:id,expense_id,maintenance_type',
                'agency:id,uuid,name',
            ])
            ->where('status', ExpenseStatus::Approved->value)
            ->where('currency', self::CURRENCY)
            ->where('occurred_at', '>=', $period->startUtc())
            ->where('occurred_at', '<', $period->endExclusiveUtc())
            ->get(['id', 'uuid', 'reference', 'agency_id', 'bus_id', 'line_id', 'departure_id', 'expense_category_id', 'amount', 'occurred_at']);

        $departuresByBus = [];
        $departuresByLine = [];
        foreach ($departureMeta as $departureId => $meta) {
            if ($meta['busId'] !== null) {
                $departuresByBus[$meta['busId']][] = $departureId;
            }
            if ($meta['lineId'] !== null) {
                $departuresByLine[$meta['lineId']][] = $departureId;
            }
        }

        $byDeparture = [];
        $byBus = [];
        $byLine = [];
        $byAgency = [];
        $categoryTotals = [];
        $total = 0;
        $assigned = 0;
        $fixedTotal = 0;
        $variableTotal = 0;

        foreach ($expenses as $expense) {
            $amount = (int) $expense->amount;
            $code = CostCategory::code($expense);
            $behavior = CostCategory::behavior($expense);
            $rule = CostCategory::rule($expense);
            $total += $amount;
            $categoryTotals[$code] = ($categoryTotals[$code] ?? 0) + $amount;

            if ($behavior === CostBehavior::Fixed) {
                $fixedTotal += $amount;
            } else {
                $variableTotal += $amount;
            }

            if ($expense->departure_id !== null && isset($departureMeta[$expense->departure_id])) {
                $departureId = (int) $expense->departure_id;
                $this->add($byDeparture, $departureId, $amount, $code, $behavior, 'direct');
                $this->add($byBus, $departureMeta[$departureId]['busId'], $amount, $code, $behavior, 'direct');
                $this->add($byLine, $departureMeta[$departureId]['lineId'], $amount, $code, $behavior, 'direct');
                $assigned += $amount;

                continue;
            }

            if ($expense->bus_id !== null) {
                $busId = (int) $expense->bus_id;
                $this->add($byBus, $busId, $amount, $code, $behavior, 'direct');
                $assigned += $amount;
                $departureIds = $departuresByBus[$busId] ?? [];

                foreach ($this->allocate($amount, $departureIds, $departureMeta, $rule) as $departureId => $share) {
                    $this->add($byDeparture, $departureId, $share, $code, $behavior, 'distributed');
                    $this->add($byLine, $departureMeta[$departureId]['lineId'], $share, $code, $behavior, 'distributed');
                }

                continue;
            }

            if ($expense->line_id !== null) {
                $lineId = (int) $expense->line_id;
                $this->add($byLine, $lineId, $amount, $code, $behavior, 'direct');
                $assigned += $amount;
                $departureIds = $departuresByLine[$lineId] ?? [];

                foreach ($this->allocate($amount, $departureIds, $departureMeta, $rule) as $departureId => $share) {
                    $this->add($byDeparture, $departureId, $share, $code, $behavior, 'distributed');
                    $this->add($byBus, $departureMeta[$departureId]['busId'], $share, $code, $behavior, 'distributed');
                }

                continue;
            }

            $agencyId = (int) $expense->agency_id;
            $byAgency[$agencyId] ??= $this->agencyMetric($expense);
            $this->addAgency($byAgency[$agencyId], $amount, $code, $behavior);
        }

        $byDeparture = $this->finalize($byDeparture);
        $byBus = $this->finalize($byBus);
        $byLine = $this->finalize($byLine);
        $byAgency = $this->finalize($byAgency);

        return [
            'total' => $total,
            'assigned' => $assigned,
            'unassigned' => $total - $assigned,
            'coveragePercent' => $total === 0 ? 0.0 : round($assigned / $total * 100, 1),
            'fixedTotal' => $fixedTotal,
            'variableTotal' => $variableTotal,
            'byDeparture' => $byDeparture,
            'byBus' => $byBus,
            'byLine' => $byLine,
            'byAgency' => $byAgency,
            'categoryTotals' => $categoryTotals,
            'missingCenterCount' => $expenses->filter(fn (Expense $expense): bool => $expense->bus_id === null
                && $expense->line_id === null
                && $expense->departure_id === null)->count(),
            'pendingCount' => Expense::query()
                ->where('status', ExpenseStatus::Pending->value)
                ->where('currency', self::CURRENCY)
                ->where('occurred_at', '>=', $period->startUtc())
                ->where('occurred_at', '<', $period->endExclusiveUtc())
                ->count(),
            'nonXofCount' => Expense::query()
                ->where('status', ExpenseStatus::Approved->value)
                ->where('currency', '!=', self::CURRENCY)
                ->where('occurred_at', '>=', $period->startUtc())
                ->where('occurred_at', '<', $period->endExclusiveUtc())
                ->count(),
        ];
    }

    /**
     * @param  list<int>  $departureIds
     * @param  array<int, array<string, mixed>>  $departureMeta
     * @return array<int, int>
     */
    private function allocate(int $amount, array $departureIds, array $departureMeta, CostAllocationRule $rule): array
    {
        if ($departureIds === []) {
            return [];
        }

        $weights = [];
        foreach ($departureIds as $departureId) {
            $weights[$departureId] = $rule === CostAllocationRule::BusKm
                ? (float) ($departureMeta[$departureId]['distanceKm'] ?? 0.0)
                : 1.0;
        }

        $shares = [];
        foreach (IntegerAllocator::distribute($amount, $weights) as $departureId => $share) {
            $shares[(int) $departureId] = $share;
        }

        return $shares;
    }

    /**
     * @param  array<int, array<string, mixed>>  $group
     * @return array<int, array<string, mixed>>
     */
    private function finalize(array $group): array
    {
        foreach ($group as $key => $metric) {
            $group[$key]['total'] = $metric['direct'] + $metric['distributed'];
        }

        return $group;
    }

    /**
     * @param  array<int, array<string, mixed>>  $group
     */
    private function add(array &$group, ?int $key, int $amount, string $code, CostBehavior $behavior, string $layer): void
    {
        if ($key === null) {
            return;
        }

        $group[$key] ??= $this->metric();
        $group[$key][$layer] += $amount;
        $group[$key][$behavior === CostBehavior::Fixed ? 'fixed' : 'variable'] += $amount;
        $group[$key]['categories'][$code] = ($group[$key]['categories'][$code] ?? 0) + $amount;
    }

    /** @param array<string, mixed> $metric */
    private function addAgency(array &$metric, int $amount, string $code, CostBehavior $behavior): void
    {
        $metric['direct'] += $amount;
        $metric[$behavior === CostBehavior::Fixed ? 'fixed' : 'variable'] += $amount;
        $metric['categories'][$code] = ($metric['categories'][$code] ?? 0) + $amount;
    }

    /** @return array<string, mixed> */
    private function agencyMetric(Expense $expense): array
    {
        return $this->metric() + [
            'uuid' => $expense->agency?->uuid,
            'name' => $expense->agency->name ?? 'Agence inconnue',
        ];
    }

    /** @return array<string, mixed> */
    private function metric(): array
    {
        return ['direct' => 0, 'distributed' => 0, 'total' => 0, 'fixed' => 0, 'variable' => 0, 'categories' => []];
    }
}
