<?php

namespace App\Modules\Profitability\Services;

use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\Profitability\Data\CostSourceStatus;
use App\Modules\Profitability\Data\EntityScope;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Classeur XLSX multi-feuilles de la rentabilité : synthèse, axes, P&L,
 * TCO, contrôles et détail par départ.
 */
class ProfitabilityExport
{
    public function __construct(
        private readonly ProfitabilityReport $report,
        private readonly FinanceExportService $exporter,
    ) {}

    private function number(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function download(RevenuePeriod $period, ProfitabilityAxis $axis, array $data, ?EntityScope $scope = null): StreamedResponse
    {
        $entitySuffix = $scope === null ? '' : '-'.$scope->type->value.'-'.Str::slug($scope->label);

        return $this->exporter->workbook(
            'rentabilite-'.$axis->value.$entitySuffix.'-'.$period->localStart->format('Ymd').'-'.$period->localEnd->format('Ymd').'.xlsx',
            $this->sheets($period, $axis, $data, $scope),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{title: string, headers: list<string>, rows: iterable<array<int, string|int|float|null>>}>
     */
    public function sheets(RevenuePeriod $period, ProfitabilityAxis $axis, array $data, ?EntityScope $scope = null): array
    {
        $hero = $data['hero'];
        $entity = is_array($data['entity'] ?? null) ? $data['entity'] : null;

        $summaryRows = [
            ['Période', $period->label()],
            ['Axe', $axis->label()],
        ];

        if ($entity !== null) {
            $summaryRows[] = ['Filtre', ProfitabilityAxis::from((string) $entity['type'])->label().' : '.$entity['label']];
        }

        return [
            [
                'title' => 'Synthèse',
                'headers' => ['Indicateur', 'Valeur'],
                'rows' => [
                    ...$summaryRows,
                    ['Généré le', (string) $data['generatedAt']],
                    ['Fuseau', RevenuePeriod::TIMEZONE],
                    ['Recettes nettes de service XOF', (int) $hero['revenueNet']],
                    ['Coûts affectés XOF', (int) $hero['costAssigned']],
                    ['Coûts non affectés XOF', (int) $hero['costUnassigned']],
                    ['Taux de couverture des coûts %', (float) $hero['coveragePercent']],
                    ['Résultat opérationnel réseau XOF', (int) $hero['result']],
                    ['Marge %', $this->number($hero['marginPercent'])],
                    ['Sièges seuil réseau', $this->number($hero['breakEven']['breakEvenSeats'])],
                    ['Statut seuil réseau', $hero['breakEven']['status']],
                    ['Source de vérité', 'Paiements confirmés rattachés aux départs, dépenses approuvées avec centre de coût.'],
                    ['Limite', 'Modèle de gestion : hors acquisition, amortissement, financement, paie et commissions KORA POINT.'],
                ],
            ],
            [
                'title' => 'Axes',
                'headers' => ['Clé', 'Libellé', 'Précision', 'Recettes nettes XOF', 'Coûts directs XOF', 'Coûts répartis XOF', 'Coûts totaux XOF', 'Contribution XOF', 'Marge %', 'Sièges vendus', 'Capacité', 'Remplissage %', 'Sièges seuil', 'Statut seuil', 'Km', 'Coût/km', 'Couverture GPS %'],
                'rows' => array_map(fn (array $card): array => [
                    (string) $card['key'],
                    (string) $card['label'],
                    (string) $card['subtitle'],
                    (int) $card['revenueNet'],
                    (int) $card['costDirect'],
                    (int) $card['costDistributed'],
                    (int) $card['costTotal'],
                    (int) $card['contribution'],
                    $this->number($card['marginPercent']),
                    (int) $card['seatsSold'],
                    $this->number($card['capacity']),
                    $this->number($card['fillRate']),
                    $this->number($card['breakEvenSeats']),
                    (string) $card['breakEvenStatus'],
                    (float) $card['km'],
                    $this->number($card['costPerKm']),
                    (float) $card['gpsCoverage'],
                ], $data['cards']),
            ],
            [
                'title' => 'P&L',
                'headers' => ['Poste', 'Source', 'Montant XOF', 'Part %', 'Évolution %', 'Statut'],
                'rows' => array_map(fn (array $row): array => [
                    (string) $row['label'],
                    (string) $row['source'],
                    $this->number($row['amount']),
                    $this->number($row['sharePercent']),
                    $this->number($row['evolutionPercent']),
                    CostSourceStatus::from($row['status'])->label(),
                ], $data['pnl']),
            ],
            [
                'title' => 'TCO',
                'headers' => ['Bus', 'TCO opérationnel XOF', 'Km', 'Coût/km', 'Recette/place XOF', 'Résultat/place XOF', 'Couverture GPS %', 'Distance'],
                'rows' => array_map(fn (array $row): array => [
                    (string) $row['label'],
                    (int) $row['tco'],
                    (float) $row['km'],
                    $this->number($row['costPerKm']),
                    $this->number($row['revenuePerSeat']),
                    $this->number($row['resultPerSeat']),
                    (float) $row['gpsCoverage'],
                    (string) $row['distanceSource'],
                ], $data['tco']),
            ],
            [
                'title' => 'Contrôles',
                'headers' => ['Contrôle', 'Valeur', 'Détail', 'Statut'],
                'rows' => [
                    ...($data['reconciliation'] === null ? [] : [
                        ['Recettes cash période XOF', (int) $data['reconciliation']['cashNet'], 'Encaissements confirmés moins annulations reconnues de la période.', 'info'],
                        ['Recettes de service XOF', (int) $data['reconciliation']['serviceNet'], 'Recettes rattachées aux départs de la période.', 'info'],
                        ['Écart de timing XOF', (int) $data['reconciliation']['timingGap'], 'Écart caisse/service expliqué par les ventes anticipées.', 'info'],
                    ]),
                    ...array_map(fn (array $control): array => [
                        (string) $control['label'],
                        (string) $control['value'],
                        (string) $control['detail'],
                        (string) $control['status'],
                    ], $data['controls']),
                ],
            ],
            [
                'title' => 'Départs',
                'headers' => ['UUID', 'Départ', 'Ligne', 'Bus', 'Statut', 'Recettes brutes XOF', 'Déductions XOF', 'Recettes nettes XOF', 'Coûts XOF', 'Coûts fixes XOF', 'Coûts variables XOF', 'Contribution XOF', 'Sièges vendus', 'Capacité', 'Km', 'Distance', 'Couverture GPS %'],
                'rows' => array_map(fn (array $row): array => [
                    (string) $row['uuid'],
                    (string) $row['scheduledAt'],
                    (string) $row['line'],
                    (string) $row['bus'],
                    (string) $row['status'],
                    (int) $row['revenueGross'],
                    (int) $row['revenueDeductions'],
                    (int) $row['revenueNet'],
                    (int) $row['costTotal'],
                    (int) $row['costFixed'],
                    (int) $row['costVariable'],
                    (int) $row['contribution'],
                    (int) $row['seatsSold'],
                    (int) $row['capacity'],
                    (float) $row['km'],
                    (string) $row['distanceSource'],
                    (float) $row['gpsCoverage'],
                ], $this->report->departureRows($period, $scope)),
            ],
        ];
    }
}
