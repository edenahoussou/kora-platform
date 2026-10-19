<?php

namespace App\Modules\Profitability\Services;

use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Finance\Services\PaymentRefundRecognizer;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;

/**
 * Rapprochement caisse/service et contrôles de qualité des données de
 * rentabilité (couverture, annulations post-période, devises, GPS).
 */
class ProfitabilityControls
{
    private const CURRENCY = 'XOF';

    public function __construct(private readonly PaymentRefundRecognizer $refundRecognizer) {}

    /**
     * @param  array<string, mixed>  $revenue
     * @param  array<string, mixed>  $costs
     * @param  array<int, array<string, mixed>>  $distances
     * @param  array<int, array<string, mixed>>  $departureMeta
     * @return array<string, mixed>
     */
    public function build(RevenuePeriod $period, array $revenue, array $costs, array $distances, array $departureMeta): array
    {
        $cashGross = (int) Payment::query()
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', self::CURRENCY)
            ->where('confirmed_at', '>=', $period->startUtc())
            ->where('confirmed_at', '<', $period->endExclusiveUtc())
            ->sum('amount');

        $cashDeductions = 0;
        foreach ($this->refundRecognizer->forPeriod($period)['current'] as $refund) {
            $cashDeductions += $refund->recognized;
        }

        $cashNet = $cashGross - $cashDeductions;
        $serviceNet = (int) $revenue['total']['net'];
        $timingGap = $cashNet - $serviceNet;

        $estimatedDepartures = count(array_filter($distances, static fn (array $distance): bool => ($distance['source'] ?? 'estimated') !== 'gps'));
        $departuresWithoutBus = count(array_filter($departureMeta, static fn (array $meta): bool => ($meta['busId'] ?? null) === null));
        $postPeriodDeductions = (int) $revenue['postPeriodDeductions'];

        return [
            'reconciliation' => [
                'cashGross' => $cashGross,
                'cashDeductions' => $cashDeductions,
                'cashNet' => $cashNet,
                'serviceNet' => $serviceNet,
                'timingGap' => $timingGap,
                'label' => $period->label(),
            ],
            'controls' => [
                [
                    'key' => 'timing',
                    'label' => 'Rapprochement caisse / service',
                    'value' => $timingGap === 0 ? 'Aligné' : $this->signed($timingGap),
                    'detail' => 'Écart entre les encaissements de la période et les recettes rattachées aux départs.',
                    'status' => $timingGap === 0 ? 'ok' : 'info',
                ],
                [
                    'key' => 'coverage',
                    'label' => 'Couverture des coûts',
                    'value' => $costs['coveragePercent'].' %',
                    'detail' => $costs['missingCenterCount'].' dépense(s) sans centre de coût sur la période.',
                    'status' => $costs['coveragePercent'] >= 80 ? 'ok' : 'warning',
                ],
                [
                    'key' => 'post_period_refunds',
                    'label' => 'Annulations post-période',
                    'value' => $postPeriodDeductions === 0 ? 'Aucune' : $this->signed(-$postPeriodDeductions),
                    'detail' => 'Annulations créées après la fin de période, déjà déduites de la recette de service.',
                    'status' => $postPeriodDeductions === 0 ? 'ok' : 'info',
                ],
                [
                    'key' => 'currencies',
                    'label' => 'Hors XOF',
                    'value' => (string) ($revenue['nonXofPaymentCount'] + $costs['nonXofCount']),
                    'detail' => 'Paiements et dépenses non XOF exclus du calcul.',
                    'status' => $revenue['nonXofPaymentCount'] + $costs['nonXofCount'] === 0 ? 'ok' : 'warning',
                ],
                [
                    'key' => 'gps',
                    'label' => 'Distance estimée',
                    'value' => $estimatedDepartures.' départ(s)',
                    'detail' => 'Couverture GPS insuffisante ou session absente : distance planifiée utilisée.',
                    'status' => $estimatedDepartures === 0 ? 'ok' : 'warning',
                ],
                [
                    'key' => 'departures_without_bus',
                    'label' => 'Départs sans bus',
                    'value' => (string) $departuresWithoutBus,
                    'detail' => 'Sans bus affecté, le coût/km et le TCO ne peuvent pas être calculés.',
                    'status' => $departuresWithoutBus === 0 ? 'ok' : 'warning',
                ],
                [
                    'key' => 'pending_expenses',
                    'label' => 'Dépenses en attente',
                    'value' => (string) $costs['pendingCount'],
                    'detail' => 'Dépenses non approuvées, donc exclues des coûts de la période.',
                    'status' => $costs['pendingCount'] === 0 ? 'ok' : 'info',
                ],
            ],
        ];
    }

    private function signed(int $amount): string
    {
        return ($amount > 0 ? '+' : '').number_format($amount, 0, ',', ' ');
    }
}
