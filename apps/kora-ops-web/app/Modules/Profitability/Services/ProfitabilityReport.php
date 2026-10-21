<?php

namespace App\Modules\Profitability\Services;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Profitability\Data\CostCategory;
use App\Modules\Profitability\Data\CostSourceStatus;
use App\Modules\Profitability\Data\EntityScope;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use Carbon\CarbonImmutable;

/**
 * Orchestrateur de la rentabilité : recettes, coûts, TCO, seuil, P&L et
 * contrôles pour l'axe demandé, avec comparaison à la période précédente.
 */
class ProfitabilityReport
{
    public function __construct(
        private readonly RevenueAttribution $revenueAttribution,
        private readonly CostAttribution $costAttribution,
        private readonly GpsDistanceCalculator $gpsDistance,
        private readonly BreakEvenCalculator $breakEven,
        private readonly ProfitabilityControls $controls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(RevenuePeriod $period, ProfitabilityAxis $axis, ?EntityScope $scope = null): array
    {
        return $this->analyze($period, $axis, $scope)['report'];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(RevenuePeriod $period, ProfitabilityAxis $axis, string $key, ?EntityScope $scope = null): array
    {
        $analysis = $this->analyze($period, $axis, $scope);
        /** @var list<array<string, mixed>> $cards */
        $cards = $analysis['report']['cards'];
        $card = collect($cards)->firstWhere('key', $key);

        if ($card === null) {
            return ['card' => null];
        }

        $financials = $analysis['financials'];
        $meta = $financials['departureMeta'];
        $agencyKey = $this->agencyKey($financials, $key);
        $entityId = $axis === ProfitabilityAxis::Agency
            ? $this->agencyId($agencyKey)
            : $this->entityId($financials, $axis, $key);

        $departureIds = match ($axis) {
            ProfitabilityAxis::Bus => array_keys(array_filter($meta, fn (array $item): bool => $item['busId'] === $entityId)),
            ProfitabilityAxis::Line => array_keys(array_filter($meta, fn (array $item): bool => $item['lineId'] === $entityId)),
            ProfitabilityAxis::Departure => array_keys(array_filter($meta, fn (array $item): bool => $item['uuid'] === $key)),
            ProfitabilityAxis::Agency => array_keys($financials['revenue']['byAgencyDeparture'][$agencyKey] ?? []),
            ProfitabilityAxis::Period => array_keys($meta),
        };

        $departures = [];
        foreach ($departureIds as $departureId) {
            $item = $meta[$departureId];
            $departures[] = [
                'uuid' => $item['uuid'],
                'label' => $item['lineCode'].' · '.$item['busLabel'],
                'scheduledAt' => $item['scheduledAt'],
                'status' => $item['status'],
                'revenueNet' => (int) ($financials['revenue']['byDeparture'][$departureId]['net'] ?? 0),
                'costTotal' => (int) ($financials['costs']['byDeparture'][$departureId]['total'] ?? 0),
                'seatsSold' => (int) ($financials['revenue']['byDeparture'][$departureId]['seatsSold'] ?? 0),
                'distanceKm' => (float) $item['distanceKm'],
                'distanceSource' => $item['distanceSource'],
                'coveragePercent' => (float) $item['coveragePercent'],
            ];
        }

        usort($departures, fn (array $left, array $right): int => $right['scheduledAt'] <=> $left['scheduledAt']);

        return [
            'card' => $card,
            'axis' => $axis->value,
            'revenue' => $this->axisRevenue($financials, $axis, $entityId, $agencyKey),
            'costs' => $this->axisCosts($financials, $axis, $entityId),
            'categories' => $this->axisCategories($financials, $axis, $entityId),
            'departures' => array_slice($departures, 0, 50),
        ];
    }

    /**
     * Lignes détaillées par départ pour l'export XLSX.
     *
     * @return list<array<string, mixed>>
     */
    public function departureRows(RevenuePeriod $period, ?EntityScope $scope = null): array
    {
        $financials = $this->financials($period, $scope);
        $rows = [];

        foreach ($financials['departureMeta'] as $departureId => $meta) {
            $revenue = $financials['revenue']['byDeparture'][$departureId] ?? ['gross' => 0, 'deductions' => 0, 'net' => 0, 'seatsSold' => 0];
            $cost = $financials['costs']['byDeparture'][$departureId] ?? ['total' => 0, 'fixed' => 0, 'variable' => 0];

            $rows[] = [
                'uuid' => $meta['uuid'],
                'scheduledAt' => CarbonImmutable::parse($meta['scheduledAt'])->setTimezone(RevenuePeriod::TIMEZONE)->toDateTimeString(),
                'line' => $meta['lineCode'].' — '.$meta['lineName'],
                'bus' => $meta['busLabel'],
                'status' => $meta['status'],
                'revenueGross' => (int) $revenue['gross'],
                'revenueDeductions' => (int) $revenue['deductions'],
                'revenueNet' => (int) $revenue['net'],
                'costTotal' => (int) $cost['total'],
                'costFixed' => (int) $cost['fixed'],
                'costVariable' => (int) $cost['variable'],
                'contribution' => (int) $revenue['net'] - (int) $cost['total'],
                'seatsSold' => (int) $revenue['seatsSold'],
                'capacity' => (int) $meta['capacity'],
                'km' => (float) $meta['distanceKm'],
                'distanceSource' => $meta['distanceSource'],
                'gpsCoverage' => (float) $meta['coveragePercent'],
            ];
        }

        usort($rows, fn (array $left, array $right): int => $left['scheduledAt'] <=> $right['scheduledAt']);

        return $rows;
    }

    /**
     * @return array{report: array<string, mixed>, financials: array<string, mixed>}
     */
    private function analyze(RevenuePeriod $period, ProfitabilityAxis $axis, ?EntityScope $scope = null): array
    {
        $financials = $this->financials($period, $scope);
        $previous = $this->financials($period->comparisonPeriod(), $scope);
        $cards = $this->cards($axis, $financials, $previous, $period);

        $revenueNet = (int) $financials['revenue']['total']['net'];
        $costTotal = (int) $financials['costs']['total'];
        $result = $revenueNet - $costTotal;
        $seatsSold = array_sum(array_column($financials['revenue']['byDeparture'], 'seatsSold'));
        $capacity = array_sum(array_map(
            static fn (array $item): int => (int) ($item['capacity'] ?? 0),
            $financials['departureMeta'],
        ));

        $networkBreakEven = $this->breakEven->calculate([
            'revenueNet' => $revenueNet,
            'costFixed' => (int) $financials['costs']['fixedTotal'],
            'costVariable' => (int) $financials['costs']['variableTotal'],
            'seatsSold' => $seatsSold,
            'capacity' => $capacity > 0 ? $capacity : null,
        ]);

        $controls = $this->controls->build(
            $period,
            $financials['revenue'],
            $financials['costs'],
            $financials['distances'],
            $financials['departureMeta'],
            $scope,
        );

        $report = [
            'currency' => 'XOF',
            'axis' => $axis->value,
            'generatedAt' => CarbonImmutable::now('UTC')->toIso8601String(),
            'entity' => $scope?->toArray(),
            'hero' => [
                'revenueNet' => $revenueNet,
                'costAssigned' => (int) $financials['costs']['assigned'],
                'costUnassigned' => (int) $financials['costs']['unassigned'],
                'networkUnassigned' => (int) $financials['costs']['networkUnassigned'],
                'result' => $result,
                'marginPercent' => $revenueNet > 0 ? round($result / $revenueNet * 100, 1) : null,
                'coveragePercent' => (float) $financials['costs']['coveragePercent'],
                'breakEven' => $networkBreakEven,
            ],
            'kpis' => [
                'revenueNet' => $revenueNet,
                'costAssigned' => (int) $financials['costs']['assigned'],
                'costUnassigned' => (int) $financials['costs']['unassigned'],
                'coveragePercent' => (float) $financials['costs']['coveragePercent'],
                'tco' => $financials['costs']['byBus'] === [] ? 0 : (int) array_sum(array_column($financials['costs']['byBus'], 'total')),
                'costPerKm' => $this->costPerKm(
                    (int) array_sum(array_column($financials['costs']['byBus'], 'total')),
                    (float) array_sum(array_column($financials['departureMeta'], 'distanceKm')),
                ),
            ],
            'cards' => $cards,
            'breakEvenCards' => array_slice($cards, 0, 3),
            'pnl' => $this->pnl($financials, $previous),
            'tco' => $scope === null || $scope->type === ProfitabilityAxis::Bus
                ? $this->tco($financials)
                : [],
            'reconciliation' => $controls['reconciliation'],
            'controls' => $controls['controls'],
            'empty' => $revenueNet === 0 && $costTotal === 0 && $financials['departureMeta'] === [],
        ];

        return ['report' => $report, 'financials' => $financials];
    }

    /**
     * @return array<string, mixed>
     */
    private function financials(RevenuePeriod $period, ?EntityScope $scope = null): array
    {
        $revenue = $this->revenueAttribution->build($period, $scope);
        /** @var array<int, array<string, mixed>> $departureMeta */
        $departureMeta = $revenue['departureMeta'];
        $distances = $this->gpsDistance->forDepartures(array_keys($departureMeta));

        $busIds = [];
        $lineIds = [];
        foreach ($departureMeta as $departureId => $meta) {
            $departureMeta[$departureId]['distanceKm'] = (float) ($distances[$departureId]['distanceKm'] ?? 0.0);
            $departureMeta[$departureId]['coveragePercent'] = (float) ($distances[$departureId]['coveragePercent'] ?? 0.0);
            $departureMeta[$departureId]['distanceSource'] = (string) ($distances[$departureId]['source'] ?? 'estimated');

            if ($meta['busId'] !== null) {
                $busIds[$meta['busId']] = $meta['busId'];
            }
            if ($meta['lineId'] !== null) {
                $lineIds[$meta['lineId']] = $meta['lineId'];
            }
        }

        $buses = $busIds === []
            ? collect()
            : Bus::query()->withTrashed()->whereIn('id', array_values($busIds))->get(['id', 'uuid', 'registration_number', 'brand', 'model', 'seat_count'])->keyBy('id');
        $lines = $lineIds === []
            ? collect()
            : Line::query()->withTrashed()->whereIn('id', array_values($lineIds))->get(['id', 'uuid', 'code', 'name'])->keyBy('id');

        /** @var array<int, mixed> $busMap */
        $busMap = $buses->all();
        /** @var array<int, mixed> $lineMap */
        $lineMap = $lines->all();

        foreach ($departureMeta as $departureId => $meta) {
            $bus = $meta['busId'] === null ? null : ($busMap[$meta['busId']] ?? null);
            $line = $meta['lineId'] === null ? null : ($lineMap[$meta['lineId']] ?? null);
            $departureMeta[$departureId]['busUuid'] = $bus === null ? null : (string) $bus->uuid;
            $departureMeta[$departureId]['busLabel'] = $bus === null ? 'Bus non affecté' : (string) $bus->registration_number;
            $departureMeta[$departureId]['busSeatCount'] = $bus === null ? null : (int) $bus->seat_count;
            $departureMeta[$departureId]['lineUuid'] = $line === null ? null : (string) $line->uuid;
            $departureMeta[$departureId]['lineCode'] = $line === null ? 'Ligne inconnue' : (string) $line->code;
            $departureMeta[$departureId]['lineName'] = $line === null ? 'Ligne inconnue' : (string) $line->name;
            $departureMeta[$departureId]['capacity'] = $bus === null ? 0 : (int) $bus->seat_count;
        }

        $costs = $this->costAttribution->build($period, $departureMeta, $scope);

        return [
            'period' => $period,
            'revenue' => $revenue,
            'costs' => $costs,
            'distances' => $distances,
            'departureMeta' => $departureMeta,
            'buses' => $buses,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $financials
     * @param  array<string, mixed>  $previous
     * @return list<array<string, mixed>>
     */
    private function cards(ProfitabilityAxis $axis, array $financials, array $previous, RevenuePeriod $period): array
    {
        $cards = match ($axis) {
            ProfitabilityAxis::Bus => $this->busCards($financials),
            ProfitabilityAxis::Line => $this->lineCards($financials),
            ProfitabilityAxis::Departure => $this->departureCards($financials),
            ProfitabilityAxis::Agency => $this->agencyCards($financials),
            ProfitabilityAxis::Period => $this->periodCards($financials, $previous, $period),
        };

        return array_map(
            static fn (array $card): array => $card + ['axisType' => $axis->value],
            $this->sortByRisk($cards),
        );
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return list<array<string, mixed>>
     */
    private function busCards(array $financials): array
    {
        $ids = array_unique([
            ...array_keys($financials['revenue']['byBus']),
            ...array_keys($financials['costs']['byBus']),
            ...array_values(array_filter(array_column($financials['departureMeta'], 'busId'))),
        ]);

        /** @var array<int, mixed> $busMap */
        $busMap = $financials['buses']->all();
        $cards = [];
        foreach ($ids as $busId) {
            $departures = array_filter($financials['departureMeta'], fn (array $meta): bool => $meta['busId'] === $busId);
            $bus = $busMap[(int) $busId] ?? null;
            $revenue = $financials['revenue']['byBus'][$busId] ?? ['net' => 0, 'seatsSold' => 0];
            $cost = $financials['costs']['byBus'][$busId] ?? ['total' => 0, 'direct' => 0, 'distributed' => 0, 'fixed' => 0, 'variable' => 0];
            $subtitle = $bus === null
                ? 'Bus non renseigné'
                : (trim(((string) $bus->brand).' '.((string) $bus->model)) ?: 'Bus non renseigné');
            $cards[] = $this->card(
                key: $bus === null ? 'bus-'.$busId : (string) $bus->uuid,
                label: $bus === null ? 'Bus '.$busId : (string) $bus->registration_number,
                subtitle: $subtitle,
                revenueNet: (int) $revenue['net'],
                cost: $cost,
                seatsSold: (int) $revenue['seatsSold'],
                capacity: $bus === null ? null : (int) $bus->seat_count,
                departureIds: array_map(static fn (int|string $id): int => (int) $id, array_keys($departures)),
                meta: $financials['departureMeta'],
            );
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return list<array<string, mixed>>
     */
    private function lineCards(array $financials): array
    {
        $ids = array_unique([
            ...array_keys($financials['revenue']['byLine']),
            ...array_keys($financials['costs']['byLine']),
            ...array_values(array_filter(array_column($financials['departureMeta'], 'lineId'))),
        ]);

        /** @var array<int, mixed> $lineMap */
        $lineMap = $financials['lines']->all();
        $cards = [];
        foreach ($ids as $lineId) {
            $departures = array_filter($financials['departureMeta'], fn (array $meta): bool => $meta['lineId'] === $lineId);
            $line = $lineMap[(int) $lineId] ?? null;
            $revenue = $financials['revenue']['byLine'][$lineId] ?? ['net' => 0, 'seatsSold' => 0];
            $cost = $financials['costs']['byLine'][$lineId] ?? ['total' => 0, 'direct' => 0, 'distributed' => 0, 'fixed' => 0, 'variable' => 0];
            $capacity = (int) array_sum(array_column($departures, 'capacity'));
            $cards[] = $this->card(
                key: $line === null ? 'line-'.$lineId : (string) $line->uuid,
                label: $line === null ? 'Ligne '.$lineId : (string) $line->code,
                subtitle: $line === null ? 'Ligne inconnue' : (string) $line->name,
                revenueNet: (int) $revenue['net'],
                cost: $cost,
                seatsSold: (int) $revenue['seatsSold'],
                capacity: $capacity > 0 ? $capacity : null,
                departureIds: array_map(static fn (int|string $id): int => (int) $id, array_keys($departures)),
                meta: $financials['departureMeta'],
            );
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return list<array<string, mixed>>
     */
    private function departureCards(array $financials): array
    {
        $cards = [];

        foreach ($financials['departureMeta'] as $departureId => $meta) {
            $revenue = $financials['revenue']['byDeparture'][$departureId] ?? ['net' => 0, 'seatsSold' => 0];
            $cost = $financials['costs']['byDeparture'][$departureId] ?? ['total' => 0, 'direct' => 0, 'distributed' => 0, 'fixed' => 0, 'variable' => 0];
            $cards[] = $this->card(
                key: (string) $meta['uuid'],
                label: $meta['lineCode'].' · '.CarbonImmutable::parse($meta['scheduledAt'])->setTimezone(RevenuePeriod::TIMEZONE)->format('d/m H:i'),
                subtitle: $meta['busLabel'],
                revenueNet: (int) $revenue['net'],
                cost: $cost,
                seatsSold: (int) $revenue['seatsSold'],
                capacity: $meta['capacity'] > 0 ? (int) $meta['capacity'] : null,
                departureIds: [(int) $departureId],
                meta: $financials['departureMeta'],
            );
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return list<array<string, mixed>>
     */
    private function agencyCards(array $financials): array
    {
        $cards = [];

        foreach ($financials['revenue']['byAgency'] as $key => $revenue) {
            $agencyId = $key === 'unqualified' || $key === 'digital_without_agency'
                ? null
                : (int) str_replace('agency-', '', $key);
            $cost = $agencyId === null
                ? ['total' => 0, 'direct' => 0, 'distributed' => 0, 'fixed' => 0, 'variable' => 0]
                : ($financials['costs']['byAgency'][$agencyId] ?? ['total' => 0, 'direct' => 0, 'distributed' => 0, 'fixed' => 0, 'variable' => 0]);
            $departures = array_keys($financials['revenue']['byAgencyDeparture'][$key] ?? []);

            $cards[] = $this->card(
                key: (string) ($revenue['uuid'] ?? $key),
                label: (string) $revenue['name'],
                subtitle: 'Recette de vente de l’agence',
                revenueNet: (int) $revenue['net'],
                cost: $cost,
                seatsSold: (int) $revenue['seatsSold'],
                capacity: null,
                departureIds: array_map(static fn (int|string $id): int => (int) $id, $departures),
                meta: $financials['departureMeta'],
            );
        }

        foreach ($financials['costs']['byAgency'] as $agencyId => $cost) {
            $key = 'agency-'.$agencyId;
            if (isset($financials['revenue']['byAgency'][$key])) {
                continue;
            }

            $cards[] = $this->card(
                key: $key,
                label: (string) $cost['name'],
                subtitle: 'Coûts non affectés de l’agence',
                revenueNet: 0,
                cost: $cost,
                seatsSold: 0,
                capacity: null,
                departureIds: [],
                meta: $financials['departureMeta'],
            );
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @param  array<string, mixed>  $previous
     * @return list<array<string, mixed>>
     */
    private function periodCards(array $financials, array $previous, RevenuePeriod $period): array
    {
        return [
            $this->periodCard('current', 'Période courante', $period->label(), $financials),
            $this->periodCard('previous', 'Période précédente', $period->comparisonPeriod()->label(), $previous),
        ];
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return array<string, mixed>
     */
    private function periodCard(string $key, string $label, string $subtitle, array $financials): array
    {
        $revenueNet = (int) $financials['revenue']['total']['net'];
        $seatsSold = array_sum(array_column($financials['revenue']['byDeparture'], 'seatsSold'));
        $capacity = array_sum(array_map(static fn (array $meta): int => (int) ($meta['capacity'] ?? 0), $financials['departureMeta']));

        return $this->card(
            key: $key,
            label: $label,
            subtitle: $subtitle,
            revenueNet: $revenueNet,
            cost: [
                'total' => (int) $financials['costs']['total'],
                'direct' => (int) $financials['costs']['assigned'],
                'distributed' => 0,
                'fixed' => (int) $financials['costs']['fixedTotal'],
                'variable' => (int) $financials['costs']['variableTotal'],
            ],
            seatsSold: $seatsSold,
            capacity: $capacity > 0 ? $capacity : null,
            departureIds: array_map(static fn (int|string $id): int => (int) $id, array_keys($financials['departureMeta'])),
            meta: $financials['departureMeta'],
        );
    }

    /**
     * @param  array<string, mixed>  $cost
     * @param  list<int>  $departureIds
     * @param  array<int, array<string, mixed>>  $meta
     * @return array<string, mixed>
     */
    private function card(
        string $key,
        string $label,
        string $subtitle,
        int $revenueNet,
        array $cost,
        int $seatsSold,
        ?int $capacity,
        array $departureIds,
        array $meta,
    ): array {
        $costTotal = (int) ($cost['total'] ?? 0);
        $contribution = $revenueNet - $costTotal;
        $distanceKm = 0.0;
        $coverageTotal = 0.0;
        $estimatedCount = 0;

        foreach ($departureIds as $departureId) {
            $distanceKm += (float) ($meta[$departureId]['distanceKm'] ?? 0.0);
            $coverageTotal += (float) ($meta[$departureId]['coveragePercent'] ?? 0.0);
            if (($meta[$departureId]['distanceSource'] ?? 'estimated') !== 'gps') {
                $estimatedCount++;
            }
        }

        $breakEven = $this->breakEven->calculate([
            'revenueNet' => $revenueNet,
            'costFixed' => (int) ($cost['fixed'] ?? 0),
            'costVariable' => (int) ($cost['variable'] ?? 0),
            'seatsSold' => $seatsSold,
            'capacity' => $capacity,
        ]);

        return [
            'key' => $key,
            'label' => $label,
            'subtitle' => $subtitle,
            'revenueNet' => $revenueNet,
            'costDirect' => (int) ($cost['direct'] ?? 0),
            'costDistributed' => (int) ($cost['distributed'] ?? 0),
            'costTotal' => $costTotal,
            'costFixed' => (int) ($cost['fixed'] ?? 0),
            'costVariable' => (int) ($cost['variable'] ?? 0),
            'contribution' => $contribution,
            'marginPercent' => $revenueNet > 0 ? round($contribution / $revenueNet * 100, 1) : null,
            'seatsSold' => $seatsSold,
            'capacity' => $capacity,
            'contributionPerSeat' => $breakEven['contributionPerSeat'],
            'breakEvenSeats' => $breakEven['breakEvenSeats'],
            'fillRate' => $breakEven['fillRate'],
            'breakEvenStatus' => $breakEven['status'],
            'km' => round($distanceKm, 1),
            'costPerKm' => $this->costPerKm($costTotal, $distanceKm),
            'revenuePerSeat' => $seatsSold > 0 ? (int) round($revenueNet / $seatsSold) : null,
            'resultPerSeat' => $seatsSold > 0 ? (int) round($contribution / $seatsSold) : null,
            'gpsCoverage' => $departureIds === [] ? 0.0 : round($coverageTotal / count($departureIds), 1),
            'distanceSource' => $departureIds === [] || $estimatedCount === count($departureIds) ? 'estimated' : ($estimatedCount === 0 ? 'gps' : 'mixed'),
            'departureCount' => count($departureIds),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function sortByRisk(array $cards): array
    {
        $order = [
            BreakEvenCalculator::STATUS_SOUS_SEUIL => 0,
            BreakEvenCalculator::STATUS_NON_CALCULABLE => 1,
            BreakEvenCalculator::STATUS_ATTEINT => 2,
        ];

        usort($cards, fn (array $left, array $right): int => [$order[$left['breakEvenStatus']] ?? 1, $left['marginPercent'] ?? PHP_INT_MIN]
            <=> [$order[$right['breakEvenStatus']] ?? 1, $right['marginPercent'] ?? PHP_INT_MIN]);

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @param  array<string, mixed>  $previous
     * @return list<array<string, mixed>>
     */
    private function pnl(array $financials, array $previous): array
    {
        $revenueNet = (int) $financials['revenue']['total']['net'];
        $previousRevenueNet = (int) $previous['revenue']['total']['net'];
        $costs = $financials['costs'];
        $previousCosts = $previous['costs'];

        $rows = [[
            'key' => 'ticket_revenue',
            'label' => 'CA billets',
            'source' => 'KORA OPS',
            'amount' => $revenueNet,
            'status' => CostSourceStatus::Available->value,
            'sharePercent' => $revenueNet > 0 ? 100.0 : null,
            'evolutionPercent' => $this->evolution($revenueNet, $previousRevenueNet),
        ], [
            'key' => 'parcel_revenue',
            'label' => 'CA colis',
            'source' => 'Module Colis',
            'amount' => null,
            'status' => CostSourceStatus::OutOfScope->value,
            'sharePercent' => null,
            'evolutionPercent' => null,
        ]];

        foreach (CostCategory::POST_ORDER as $code) {
            $amount = -(int) ($costs['categoryTotals'][$code] ?? 0);
            $previousAmount = -(int) ($previousCosts['categoryTotals'][$code] ?? 0);
            $rows[] = [
                'key' => 'cost_'.$code,
                'label' => CostCategory::labelForCode($code),
                'source' => 'Dépenses opérationnelles',
                'amount' => $amount,
                'status' => CostSourceStatus::Available->value,
                'sharePercent' => $revenueNet > 0 ? round($amount / $revenueNet * 100, 1) : null,
                'evolutionPercent' => $this->evolution($amount, $previousAmount),
            ];
        }

        $rows[] = [
            'key' => 'hr_casskai',
            'label' => 'RH CassKai',
            'source' => 'CassKai API',
            'amount' => null,
            'status' => CostSourceStatus::Pending->value,
            'sharePercent' => null,
            'evolutionPercent' => null,
        ];
        $rows[] = [
            'key' => 'point_commissions',
            'label' => 'Commissions KORA POINT',
            'source' => 'KORA POINT',
            'amount' => null,
            'status' => CostSourceStatus::Pending->value,
            'sharePercent' => null,
            'evolutionPercent' => null,
        ];

        $result = $revenueNet - (int) $costs['total'];
        $previousResult = $previousRevenueNet - (int) $previousCosts['total'];
        $rows[] = [
            'key' => 'result',
            'label' => 'Résultat opérationnel réseau',
            'source' => 'Calcul',
            'amount' => $result,
            'status' => CostSourceStatus::Available->value,
            'sharePercent' => $revenueNet > 0 ? round($result / $revenueNet * 100, 1) : null,
            'evolutionPercent' => $this->evolution($result, $previousResult),
        ];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return list<array<string, mixed>>
     */
    private function tco(array $financials): array
    {
        /** @var array<int, mixed> $busMap */
        $busMap = $financials['buses']->all();
        $rows = [];

        foreach ($financials['costs']['byBus'] as $busId => $cost) {
            $bus = $busMap[(int) $busId] ?? null;
            $departures = array_filter($financials['departureMeta'], fn (array $meta): bool => $meta['busId'] === $busId);
            $distanceKm = (float) array_sum(array_column($departures, 'distanceKm'));
            $coverage = $departures === [] ? 0.0 : array_sum(array_column($departures, 'coveragePercent')) / count($departures);
            $revenue = (int) ($financials['revenue']['byBus'][$busId]['net'] ?? 0);
            $seatsSold = (int) ($financials['revenue']['byBus'][$busId]['seatsSold'] ?? 0);

            $rows[] = [
                'key' => $bus === null ? 'bus-'.$busId : (string) $bus->uuid,
                'label' => $bus === null ? 'Bus '.$busId : (string) $bus->registration_number,
                'tco' => (int) $cost['total'],
                'km' => round($distanceKm, 1),
                'costPerKm' => $this->costPerKm((int) $cost['total'], $distanceKm),
                'revenuePerSeat' => $seatsSold > 0 ? (int) round($revenue / $seatsSold) : null,
                'resultPerSeat' => $seatsSold > 0 ? (int) round(($revenue - (int) $cost['total']) / $seatsSold) : null,
                'gpsCoverage' => round($coverage, 1),
                'distanceSource' => $this->distanceSource($departures),
            ];
        }

        usort($rows, fn (array $left, array $right): int => $right['tco'] <=> $left['tco']);

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $departures
     */
    private function distanceSource(array $departures): string
    {
        if ($departures === []) {
            return 'estimated';
        }

        $estimated = count(array_filter($departures, static fn (array $meta): bool => ($meta['distanceSource'] ?? 'estimated') !== 'gps'));

        return $estimated === 0 ? 'gps' : ($estimated === count($departures) ? 'estimated' : 'mixed');
    }

    private function costPerKm(int $cost, float $distanceKm): ?float
    {
        return $distanceKm > 0 ? round($cost / $distanceKm, 2) : null;
    }

    private function evolution(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round(($current - $previous) / abs($previous) * 100, 1);
    }

    /**
     * @param  array<string, mixed>  $financials
     */
    private function entityId(array $financials, ProfitabilityAxis $axis, string $key): ?int
    {
        return match ($axis) {
            ProfitabilityAxis::Bus => $financials['buses']->first(fn (Bus $bus): bool => (string) $bus->uuid === $key)?->getKey(),
            ProfitabilityAxis::Line => $financials['lines']->first(fn (Line $line): bool => (string) $line->uuid === $key)?->getKey(),
            ProfitabilityAxis::Departure => $this->departureIdByUuid($financials['departureMeta'], $key),
            ProfitabilityAxis::Agency => $this->agencyId($key),
            ProfitabilityAxis::Period => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $meta
     */
    private function departureIdByUuid(array $meta, string $uuid): ?int
    {
        foreach ($meta as $departureId => $item) {
            if ($item['uuid'] === $uuid) {
                return (int) $departureId;
            }
        }

        return null;
    }

    private function agencyId(string $key): ?int
    {
        return str_starts_with($key, 'agency-') ? (int) str_replace('agency-', '', $key) : null;
    }

    /**
     * @param  array<string, mixed>  $financials
     */
    private function agencyKey(array $financials, string $key): string
    {
        foreach ($financials['revenue']['byAgency'] as $agencyKey => $metric) {
            if ((string) ($metric['uuid'] ?? $agencyKey) === $key) {
                return (string) $agencyKey;
            }
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return array<string, mixed>
     */
    private function axisRevenue(array $financials, ProfitabilityAxis $axis, ?int $entityId, string $agencyKey): array
    {
        $revenue = $financials['revenue'];

        return match ($axis) {
            ProfitabilityAxis::Departure => $revenue['byDeparture'][$entityId] ?? [],
            ProfitabilityAxis::Bus => $revenue['byBus'][$entityId] ?? [],
            ProfitabilityAxis::Line => $revenue['byLine'][$entityId] ?? [],
            ProfitabilityAxis::Agency => $revenue['byAgency'][$agencyKey] ?? [],
            ProfitabilityAxis::Period => $revenue['total'],
        };
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return array<string, mixed>
     */
    private function axisCosts(array $financials, ProfitabilityAxis $axis, ?int $entityId): array
    {
        $costs = $financials['costs'];

        return match ($axis) {
            ProfitabilityAxis::Departure => $costs['byDeparture'][$entityId] ?? [],
            ProfitabilityAxis::Bus => $costs['byBus'][$entityId] ?? [],
            ProfitabilityAxis::Line => $costs['byLine'][$entityId] ?? [],
            ProfitabilityAxis::Agency => $costs['byAgency'][$entityId] ?? [],
            ProfitabilityAxis::Period => ['total' => $costs['total'], 'direct' => $costs['assigned'], 'distributed' => 0, 'fixed' => $costs['fixedTotal'], 'variable' => $costs['variableTotal'], 'categories' => $costs['categoryTotals']],
        };
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return array<string, int>
     */
    private function axisCategories(array $financials, ProfitabilityAxis $axis, ?int $entityId): array
    {
        $metric = $this->axisCosts($financials, $axis, $entityId);

        return is_array($metric['categories'] ?? null) ? $metric['categories'] : [];
    }
}
