<?php

namespace App\Modules\Finance\Services;

use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Finance\Data\RevenueRefundEvent;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Collection;

class ConsolidatedRevenueReport
{
    private const CURRENCY = 'XOF';

    /** @var array<string, string> */
    private const CHANNEL_LABELS = [
        'counter' => 'Vente au guichet',
        'mobile' => 'Vente via l’application passager',
        'company_portal' => 'Vente portail entreprise',
        'unclassified' => 'Canal historique à qualifier',
    ];

    /** @var array<string, string> */
    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'wallet' => 'Wallet',
        'company_credit' => 'Crédit entreprise',
    ];

    public function __construct(
        private readonly RevenueClassifier $classifier,
        private readonly PaymentRefundRecognizer $refundRecognizer,
    ) {}

    /** @return array<string, mixed> */
    public function build(RevenuePeriod $period): array
    {
        $payments = $this->paymentsForRange($period->startUtc(), $period->endExclusiveUtc(), true);
        $comparisonPayments = $this->paymentsForRange($period->comparisonStartUtc(), $period->comparisonEndExclusiveUtc());
        $refundEvents = $this->refundRecognizer->forPeriod($period);

        $summary = $this->summary($payments, $refundEvents['current']);
        $comparisonSummary = $this->summary($comparisonPayments, $refundEvents['comparison']);
        $paymentSources = $this->paymentSources($payments, $refundEvents['current']);
        $byPaymentMethod = $this->byPaymentMethod($payments, $refundEvents['current']);
        foreach ($byPaymentMethod as &$paymentMethod) {
            $paymentMethod['sources'] = array_values(array_filter(
                $paymentSources,
                fn (array $source): bool => $source['method'] === $paymentMethod['key'],
            ));
        }
        unset($paymentMethod);

        return [
            'summary' => $summary,
            'comparison' => $this->comparison($summary, $comparisonSummary),
            'timeline' => $this->timeline($period, $payments, $refundEvents['current']),
            'byChannel' => $this->byChannel($payments, $refundEvents['current']),
            'byPaymentMethod' => $byPaymentMethod,
            'paymentSources' => $paymentSources,
            'byAgency' => $this->byAgency($payments, $refundEvents['current']),
            'digitalWithoutAgency' => $this->digitalWithoutAgency($payments, $refundEvents['current']),
            'refunds' => $this->refundSummary($refundEvents['current']),
            'controls' => $this->controls($period, $payments, $refundEvents['current']),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{title: string, headers: list<string>, rows: iterable<array<int, string|int|null>>}>
     */
    public function exportSheets(RevenuePeriod $period, array $report): array
    {
        $summary = $report['summary'];
        $comparison = $report['comparison'];
        $controls = $report['controls'];
        $agencyRows = [...$report['byAgency'], $report['digitalWithoutAgency']];

        return [
            [
                'title' => 'Synthèse',
                'headers' => ['Indicateur', 'Valeur'],
                'rows' => [
                    ['Période', $period->label()],
                    ['Début', $period->localStart->toDateString()],
                    ['Fin', $period->localEnd->toDateString()],
                    ['Fuseau', RevenuePeriod::TIMEZONE],
                    ['Période comparée', $period->comparisonLocalStart->toDateString().' — '.$period->comparisonLocalEnd->toDateString()],
                    ['Recettes brutes XOF', $summary['gross']],
                    ['Déductions XOF', $summary['deductions']],
                    ['Recettes nettes XOF', $summary['net']],
                    ['Recettes nettes comparées XOF', $comparison['net']],
                    ['Variation absolue XOF', $comparison['absolute']],
                    ['Variation en %', $comparison['percentage'] === null ? 'Non calculable' : $comparison['percentage']],
                    ['Limite devise', 'Consolidation XOF uniquement ; les autres devises sont isolées dans les contrôles.'],
                    ['Limite fiscale', 'Avoirs fiscaux non disponibles.'],
                    ['Source de vérité', 'Paiements confirmés et obligations de remboursement plafonnées par paiement.'],
                ],
            ],
            $this->aggregateSheet('Agences', $agencyRows),
            $this->aggregateSheet('Canaux', $report['byChannel']),
            $this->sourceSheet($report['paymentSources']),
            [
                'title' => 'Écritures',
                'headers' => ['Type', 'Date économique', 'UUID écriture', 'UUID paiement', 'Réservation', 'Agence / périmètre', 'Canal final', 'Source d’encaissement', 'Intégration', 'Réseau', 'Montant XOF', 'Statut'],
                'rows' => $this->entryRows($period),
            ],
            [
                'title' => 'Contrôles',
                'headers' => ['Contrôle', 'Attendu', 'Observé', 'Écart / anomalie', 'Statut'],
                'rows' => [
                    ['Billets / paiements', $controls['tickets']['expected'], $controls['tickets']['allocated'], $controls['tickets']['difference'], $controls['tickets']['status']],
                    ['Mouvements de caisse', $controls['cash']['expected'], $controls['cash']['movements'], $controls['cash']['difference'], $controls['cash']['status']],
                    ['Sessions de caisse', $controls['cash']['sessionsChecked'], $controls['cash']['sessionsChecked'] - $controls['cash']['sessionMismatchCount'], $controls['cash']['sessionMismatchCount'], $controls['cash']['status']],
                    ['MECeF certifiés', $controls['mecef']['total'], $controls['mecef']['certified'], $controls['mecef']['failed'] + $controls['mecef']['missing'], $controls['mecef']['status']],
                    ['Remboursements sans paiement', 0, $controls['refunds']['unlinkedCount'], $controls['refunds']['unlinkedCount'], $controls['refunds']['status']],
                    ['Remboursements au-delà du paiement', 0, $controls['refunds']['exceededCount'], $controls['refunds']['exceededAmount'], $controls['refunds']['status']],
                    ['Paiements hors XOF', 0, $controls['currencies']['paymentCount'], $controls['currencies']['paymentCount'], $controls['currencies']['status']],
                ],
            ],
        ];
    }

    /**
     * @param  array<array<string, mixed>>  $items
     * @return array{title: string, headers: list<string>, rows: iterable<array<int, string|int|null>>}
     */
    private function aggregateSheet(string $title, array $items): array
    {
        return [
            'title' => $title,
            'headers' => ['Code', 'Libellé', 'Encaissements', 'Recettes brutes XOF', 'Déductions XOF', 'Recettes nettes XOF'],
            'rows' => array_map(fn (array $item): array => [
                $item['key'] ?? $item['uuid'] ?? 'without_agency',
                $item['label'] ?? $item['name'],
                $item['count'],
                $item['gross'],
                $item['deductions'],
                $item['net'],
            ], $items),
        ];
    }

    /**
     * @param  array<array<string, mixed>>  $items
     * @return array{title: string, headers: list<string>, rows: iterable<array<int, string|int|null>>}
     */
    private function sourceSheet(array $items): array
    {
        return [
            'title' => 'Moyens de paiement',
            'headers' => ['Code', 'Libellé', 'Contexte', 'Intégration', 'Réseau', 'Compte', 'Encaissements', 'Recettes brutes XOF', 'Déductions XOF', 'Recettes nettes XOF'],
            'rows' => array_map(fn (array $item): array => [
                $item['key'],
                $item['label'],
                $item['context'],
                $item['integration'],
                $item['network'],
                $item['account'],
                $item['count'],
                $item['gross'],
                $item['deductions'],
                $item['net'],
            ], $items),
        ];
    }

    /** @return Generator<int, array<int, string|int|null>> */
    public function entryRows(RevenuePeriod $period): Generator
    {
        $payments = Payment::query()
            ->with('reservation.sellingAgency')
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', self::CURRENCY)
            ->where('confirmed_at', '>=', $period->startUtc())
            ->where('confirmed_at', '<', $period->endExclusiveUtc())
            ->orderBy('id')
            ->lazyById(500);

        foreach ($payments as $payment) {
            $channel = $this->classifier->channel($payment);
            $source = $this->classifier->source($payment);
            $agencyName = $payment->reservation?->sellingAgency?->name;
            if ($agencyName === null) {
                $agencyName = match ($channel['key']) {
                    'mobile' => 'Application passager — hors agence',
                    'company_portal' => 'Vente portail entreprise — hors agence',
                    default => 'Attribution à qualifier',
                };
            }

            yield [
                'Encaissement',
                $payment->confirmed_at?->setTimezone(RevenuePeriod::TIMEZONE)->toIso8601String(),
                (string) $payment->uuid,
                (string) $payment->uuid,
                $payment->reservation === null ? null : (string) $payment->reservation->reference,
                $agencyName,
                $channel['label'],
                $source['label'],
                $source['integration'],
                $source['network'],
                (int) $payment->amount,
                $payment->status->value,
            ];
        }

        foreach ($this->refundRecognizer->forRange($period->startUtc(), $period->endExclusiveUtc()) as $refund) {
            if ($refund->recognized === 0) {
                continue;
            }

            $channel = $this->classifier->channelFromValues(
                $refund->channel,
                $refund->provider,
                $refund->providerReference,
                $refund->providerTransactionId,
            );
            $source = $this->classifier->sourceFromValues(
                $refund->method,
                $refund->provider,
                $refund->network,
                $channel['key'],
                $refund->providerReference,
                $refund->providerTransactionId,
            );
            $agencyName = $refund->agencyUuid === null
                ? match ($channel['key']) {
                    'mobile' => 'Application passager — hors agence',
                    'company_portal' => 'Vente portail entreprise — hors agence',
                    default => 'Attribution à qualifier',
                }
            : $refund->agencyName;

            yield [
                'Annulation',
                $refund->date->setTimezone(RevenuePeriod::TIMEZONE)->toIso8601String(),
                $refund->uuid,
                $refund->paymentUuid,
                $refund->reservationReference,
                $agencyName,
                $channel['label'],
                $source['label'],
                $source['integration'],
                $source['network'],
                -$refund->recognized,
                $refund->status,
            ];
        }
    }

    /**
     * @return Collection<int, Payment>
     */
    private function paymentsForRange(CarbonImmutable $start, CarbonImmutable $end, bool $withControls = false): Collection
    {
        $relations = ['reservation.sellingAgency', 'agencyPaymentAccount'];
        if ($withControls) {
            $relations[] = 'reservation.tickets.reservationPassenger';
        }

        return Payment::query()
            ->with($relations)
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', self::CURRENCY)
            ->where('confirmed_at', '>=', $start)
            ->where('confirmed_at', '<', $end)
            ->orderBy('confirmed_at')
            ->get();
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return array{currency: string, gross: int, deductions: int, net: int, paymentCount: int, refundCount: int, entryCount: int}
     */
    private function summary(Collection $payments, array $refunds): array
    {
        $gross = (int) $payments->sum('amount');
        $deductions = (int) array_sum(array_map(fn (RevenueRefundEvent $refund): int => $refund->recognized, $refunds));

        return [
            'currency' => self::CURRENCY,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $gross - $deductions,
            'paymentCount' => $payments->count(),
            'refundCount' => count($refunds),
            'entryCount' => $payments->count() + count(array_filter($refunds, fn (RevenueRefundEvent $refund): bool => $refund->recognized > 0)),
        ];
    }

    /**
     * @param  array{currency: string, gross: int, deductions: int, net: int, paymentCount: int, refundCount: int, entryCount: int}  $current
     * @param  array{currency: string, gross: int, deductions: int, net: int, paymentCount: int, refundCount: int, entryCount: int}  $previous
     * @return array<string, bool|float|int|string|null>
     */
    private function comparison(array $current, array $previous): array
    {
        $currentNet = (int) $current['net'];
        $previousNet = (int) $previous['net'];
        $absolute = $currentNet - $previousNet;
        $percentage = $previousNet === 0 ? null : round(($absolute / abs($previousNet)) * 100, 1);

        return [
            'gross' => $previous['gross'],
            'deductions' => $previous['deductions'],
            'net' => $previous['net'],
            'absolute' => $absolute,
            'percentage' => $percentage,
            'hasBaseline' => (int) $previous['gross'] !== 0 || (int) $previous['deductions'] !== 0,
            'direction' => $absolute === 0 ? 'stable' : ($absolute > 0 ? 'up' : 'down'),
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return list<array<string, mixed>>
     */
    private function byChannel(Collection $payments, array $refunds): array
    {
        /** @var array<string, array{key: string, label: string, gross: int, deductions: int, net: int, count: int}> $metrics */
        $metrics = [];
        foreach (self::CHANNEL_LABELS as $key => $label) {
            $metrics[$key] = $this->metric($key, $label);
        }

        foreach ($payments as $payment) {
            $channel = $this->classifier->channel($payment);
            $this->incrementMetric($metrics, $channel['key'], $channel['label'], (int) $payment->amount, 0, 1);
        }
        foreach ($refunds as $refund) {
            $channel = $this->classifier->channelFromValues(
                $refund->channel,
                $refund->provider,
                $refund->providerReference,
                $refund->providerTransactionId,
            );
            $this->incrementMetric($metrics, $channel['key'], $channel['label'], 0, $refund->recognized, 0);
        }

        return $this->finalizeMetrics($metrics);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return list<array<string, mixed>>
     */
    private function byPaymentMethod(Collection $payments, array $refunds): array
    {
        /** @var array<string, array{key: string, label: string, gross: int, deductions: int, net: int, count: int}> $metrics */
        $metrics = [];
        foreach (self::PAYMENT_METHOD_LABELS as $key => $label) {
            $metrics[$key] = $this->metric($key, $label);
        }

        foreach ($payments as $payment) {
            $key = $payment->method->value;
            if (! isset($metrics[$key])) {
                $metrics[$key] = $this->metric($key, 'Moyen à qualifier');
            }
            $metrics[$key]['gross'] += (int) $payment->amount;
            $metrics[$key]['count']++;
        }
        foreach ($refunds as $refund) {
            $key = $refund->method;
            if (! isset($metrics[$key])) {
                $metrics[$key] = $this->metric($key, 'Moyen à qualifier');
            }
            $metrics[$key]['deductions'] += $refund->recognized;
        }

        return $this->finalizeMetrics($metrics);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return list<array<string, mixed>>
     */
    private function paymentSources(Collection $payments, array $refunds): array
    {
        $metrics = [];

        foreach ($payments as $payment) {
            $source = $this->classifier->source($payment);
            $metrics[$source['key']] ??= $this->sourceMetric($source);
            if ($source['account'] !== null
                && $metrics[$source['key']]['account'] !== null
                && $metrics[$source['key']]['account'] !== $source['account']) {
                $metrics[$source['key']]['account'] = 'Plusieurs comptes';
            } elseif ($metrics[$source['key']]['account'] === null) {
                $metrics[$source['key']]['account'] = $source['account'];
            }
            $metrics[$source['key']]['gross'] += (int) $payment->amount;
            $metrics[$source['key']]['count']++;
        }

        foreach ($refunds as $refund) {
            $channel = $this->classifier->channelFromValues(
                $refund->channel,
                $refund->provider,
                $refund->providerReference,
                $refund->providerTransactionId,
            );
            $source = $this->classifier->sourceFromValues(
                $refund->method,
                $refund->provider,
                $refund->network,
                $channel['key'],
                $refund->providerReference,
                $refund->providerTransactionId,
            );
            $metrics[$source['key']] ??= $this->sourceMetric($source);
            $metrics[$source['key']]['deductions'] += $refund->recognized;
        }

        $items = $this->finalizeMetrics($metrics);
        usort($items, fn (array $left, array $right): int => $right['net'] <=> $left['net']);

        return $items;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return list<array<string, mixed>>
     */
    private function byAgency(Collection $payments, array $refunds): array
    {
        $metrics = [];
        foreach ($payments as $payment) {
            if ($payment->reservation->getAttribute('selling_agency_id') === null) {
                continue;
            }

            $agencyUuid = (string) $payment->reservation->sellingAgency->uuid;
            $agencyName = (string) $payment->reservation->sellingAgency->name;
            $key = $agencyUuid;
            $metrics[$key] ??= array_merge($this->metric($key, $agencyName), ['uuid' => $agencyUuid, 'name' => $agencyName]);
            $metrics[$key]['gross'] += (int) $payment->amount;
            $metrics[$key]['count']++;
        }
        foreach ($refunds as $refund) {
            if ($refund->agencyUuid === null) {
                continue;
            }

            $key = $refund->agencyUuid;
            $metrics[$key] ??= array_merge($this->metric($key, $refund->agencyName), ['uuid' => $refund->agencyUuid, 'name' => $refund->agencyName]);
            $metrics[$key]['deductions'] += $refund->recognized;
        }

        $items = $this->finalizeMetrics($metrics);
        usort($items, fn (array $left, array $right): int => $right['net'] <=> $left['net']);

        return $items;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return array<string, mixed>
     */
    private function digitalWithoutAgency(Collection $payments, array $refunds): array
    {
        $metric = array_merge(
            $this->metric('digital_without_agency', 'Ventes digitales — hors agence'),
            ['uuid' => null, 'name' => 'Ventes digitales — hors agence'],
        );

        foreach ($payments as $payment) {
            $channelKey = $this->classifier->channel($payment)['key'];
            if ($payment->reservation->getAttribute('selling_agency_id') !== null
                || ! in_array($channelKey, ['mobile', 'company_portal'], true)) {
                continue;
            }

            $metric['gross'] += (int) $payment->amount;
            $metric['count']++;
        }

        foreach ($refunds as $refund) {
            $channelKey = $this->classifier->channelFromValues(
                $refund->channel,
                $refund->provider,
                $refund->providerReference,
                $refund->providerTransactionId,
            )['key'];
            if ($refund->agencyUuid !== null
                || ! in_array($channelKey, ['mobile', 'company_portal'], true)) {
                continue;
            }

            $metric['deductions'] += $refund->recognized;
        }

        return $this->finalizeMetrics(['digital_without_agency' => $metric])[0];
    }

    /** @return array{key: string, label: string, gross: int, deductions: int, net: int, count: int} */
    private function metric(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'gross' => 0, 'deductions' => 0, 'net' => 0, 'count' => 0];
    }

    /**
     * @param  array<string, array{key: string, label: string, gross: int, deductions: int, net: int, count: int}>  $metrics
     */
    private function incrementMetric(array &$metrics, string $key, string $label, int $gross, int $deductions, int $count): void
    {
        if (! isset($metrics[$key])) {
            $metrics[$key] = $this->metric($key, $label);
        }

        $metrics[$key]['gross'] += $gross;
        $metrics[$key]['deductions'] += $deductions;
        $metrics[$key]['count'] += $count;
    }

    /**
     * @param  array{key: string, label: string, method: string, context: string, integration: string|null, network: string|null, account: string|null, qualified: bool}  $source
     * @return array{key: string, label: string, method: string, context: string, integration: string|null, network: string|null, account: string|null, gross: int, deductions: int, net: int, count: int}
     */
    private function sourceMetric(array $source): array
    {
        return [
            'key' => $source['key'],
            'label' => $source['label'],
            'method' => $source['method'],
            'context' => $source['context'],
            'integration' => $source['integration'],
            'network' => $source['network'],
            'account' => $source['account'],
            'gross' => 0,
            'deductions' => 0,
            'net' => 0,
            'count' => 0,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @return list<array<string, mixed>>
     */
    private function finalizeMetrics(array $metrics): array
    {
        return array_values(array_map(function (array $metric): array {
            $metric['net'] = $metric['gross'] - $metric['deductions'];

            return $metric;
        }, $metrics));
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return list<array<string, mixed>>
     */
    private function timeline(RevenuePeriod $period, Collection $payments, array $refunds): array
    {
        $points = $this->emptyTimeline($period);

        foreach ($payments as $payment) {
            $key = $this->bucketKey($period, CarbonImmutable::instance($payment->confirmed_at)->setTimezone(RevenuePeriod::TIMEZONE));
            $points[$key]['gross'] += (int) $payment->amount;
        }
        foreach ($refunds as $refund) {
            $key = $this->bucketKey($period, $refund->date->setTimezone(RevenuePeriod::TIMEZONE));
            $points[$key]['deductions'] += $refund->recognized;
        }

        foreach ($points as &$point) {
            $point['net'] = $point['gross'] - $point['deductions'];
        }
        unset($point);

        return array_values($points);
    }

    /** @return array<string, array<string, mixed>> */
    private function emptyTimeline(RevenuePeriod $period): array
    {
        $points = [];
        $cursor = $period->localStart;
        $step = $period->bucket();

        while ($cursor->lessThanOrEqualTo($period->localEnd)) {
            $key = $this->bucketKey($period, $cursor);
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
            $points[$key] = ['key' => $key, 'label' => $label, 'gross' => 0, 'deductions' => 0, 'net' => 0];
            $cursor = match ($step) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow()->startOfMonth(),
                default => $cursor->addDay(),
            };
        }

        return $points;
    }

    private function bucketKey(RevenuePeriod $period, CarbonImmutable $date): string
    {
        return match ($period->bucket()) {
            'week' => $date->startOfWeek()->max($period->localStart)->toDateString(),
            'month' => $date->startOfMonth()->max($period->localStart)->toDateString(),
            default => $date->toDateString(),
        };
    }

    /**
     * @param  list<RevenueRefundEvent>  $refunds
     * @return array<string, int>
     */
    private function refundSummary(array $refunds): array
    {
        $summary = [
            'declared' => 0,
            'deducted' => 0,
            'toCredit' => 0,
            'credited' => 0,
            'toRegularize' => 0,
            'count' => count($refunds),
        ];

        foreach ($refunds as $refund) {
            $summary['declared'] += $refund->amount;
            $summary['deducted'] += $refund->recognized;
            $key = match ($refund->status) {
                PaymentRefundStatus::Pending->value => 'toCredit',
                PaymentRefundStatus::Completed->value => 'credited',
                PaymentRefundStatus::Failed->value => 'toRegularize',
                default => null,
            };
            if ($key !== null) {
                $summary[$key] += $refund->recognized;
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<RevenueRefundEvent>  $refunds
     * @return array<string, mixed>
     */
    private function controls(RevenuePeriod $period, Collection $payments, array $refunds): array
    {
        return [
            'tickets' => $this->ticketControl($payments),
            'cash' => $this->cashControl($payments),
            'mecef' => $this->mecefControl($payments),
            'refunds' => $this->refundControl($refunds),
            'currencies' => $this->currencyControl($period),
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, int|string>
     */
    private function ticketControl(Collection $payments): array
    {
        $expected = 0;
        $allocated = 0;
        $mismatchCount = 0;

        foreach ($payments as $payment) {
            $paymentAmount = (int) $payment->amount;
            $allocations = $payment->reservation?->total_snapshot['passenger_allocations'] ?? [];
            $ticketAmount = (int) ($payment->reservation?->tickets->sum(function (Ticket $ticket) use ($allocations): int {
                $index = $ticket->reservationPassenger?->passenger_index;

                return $index === null ? 0 : (int) ($allocations[$index] ?? 0);
            }) ?? 0);
            $expected += $paymentAmount;
            $allocated += $ticketAmount;
            if ($paymentAmount !== $ticketAmount) {
                $mismatchCount++;
            }
        }

        return [
            'expected' => $expected,
            'allocated' => $allocated,
            'difference' => $allocated - $expected,
            'paymentCount' => $payments->count(),
            'matchedCount' => $payments->count() - $mismatchCount,
            'mismatchCount' => $mismatchCount,
            'status' => $mismatchCount === 0 ? 'ok' : 'warning',
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, int|string>
     */
    private function cashControl(Collection $payments): array
    {
        $cashPayments = $payments->filter(fn (Payment $payment): bool => in_array($payment->reservation?->channel, [ReservationChannel::Counter, ReservationChannel::Ops], true)
            && in_array($payment->method, [PaymentMethod::Cash, PaymentMethod::MobileMoney], true));
        $paymentIds = $cashPayments->modelKeys();
        $movementAmounts = $paymentIds === [] ? collect() : CashMovement::query()
            ->where('source_type', Payment::class)
            ->whereIn('source_id', $paymentIds)
            ->where('direction', CashMovementDirection::In->value)
            ->whereIn('status', [CashMovementStatus::Posted->value, CashMovementStatus::Approved->value])
            ->selectRaw('source_id, SUM(amount) as amount')
            ->groupBy('source_id')
            ->pluck('amount', 'source_id');

        $expected = (int) $cashPayments->sum('amount');
        $movements = (int) $movementAmounts->sum();
        $missingMovementCount = $cashPayments->filter(fn (Payment $payment): bool => (int) ($movementAmounts[$payment->getKey()] ?? 0) !== (int) $payment->amount)->count();
        $sessionIds = $cashPayments->pluck('cash_session_id')->filter()->unique()->values()->all();
        $sessionMismatchCount = 0;

        if ($sessionIds !== []) {
            $sessionPayments = Payment::query()
                ->whereIn('cash_session_id', $sessionIds)
                ->where('status', PaymentStatus::Confirmed->value)
                ->where('currency', self::CURRENCY)
                ->whereIn('method', [PaymentMethod::Cash->value, PaymentMethod::MobileMoney->value])
                ->get()
                ->groupBy('cash_session_id');
            $sessions = CashSession::query()->whereIn('id', $sessionIds)->get();

            foreach ($sessions as $session) {
                $sessionGroup = $sessionPayments->get($session->getKey(), collect());
                $cash = (int) $sessionGroup->where('method', PaymentMethod::Cash)->sum('amount');
                $mobileMoney = (int) $sessionGroup->where('method', PaymentMethod::MobileMoney)->sum('amount');
                if ($cash !== (int) $session->total_cash || $mobileMoney !== (int) $session->total_mobile_money) {
                    $sessionMismatchCount++;
                }
            }
        }

        $hasMismatch = $missingMovementCount > 0 || $sessionMismatchCount > 0;

        return [
            'expected' => $expected,
            'movements' => $movements,
            'difference' => $movements - $expected,
            'paymentCount' => $cashPayments->count(),
            'missingMovementCount' => $missingMovementCount,
            'sessionsChecked' => count($sessionIds),
            'sessionMismatchCount' => $sessionMismatchCount,
            'status' => $hasMismatch ? 'warning' : 'ok',
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, int|string>
     */
    private function mecefControl(Collection $payments): array
    {
        $invoices = $payments->isEmpty()
            ? collect()
            : FiscalInvoice::query()->whereIn('payment_id', $payments->modelKeys())->get()->keyBy('payment_id');
        $counts = ['certified' => 0, 'pending' => 0, 'failed' => 0, 'canceled' => 0, 'missing' => 0];

        foreach ($payments as $payment) {
            $status = $invoices->get($payment->getKey())?->status;
            $key = $status instanceof FiscalInvoiceStatus ? $status->value : 'missing';
            $counts[$key]++;
        }

        return array_merge($counts, [
            'total' => $payments->count(),
            'status' => $counts['failed'] > 0 || $counts['missing'] > 0 ? 'warning' : 'ok',
        ]);
    }

    /**
     * @param  list<RevenueRefundEvent>  $refunds
     * @return array<string, int|string>
     */
    private function refundControl(array $refunds): array
    {
        $unlinkedCount = count(array_filter($refunds, fn (RevenueRefundEvent $refund): bool => $refund->unlinked));
        $ineligibleCount = count(array_filter($refunds, fn (RevenueRefundEvent $refund): bool => $refund->ineligiblePayment));
        $exceeded = array_filter($refunds, fn (RevenueRefundEvent $refund): bool => $refund->excess > 0);
        $exceededAmount = (int) array_sum(array_map(fn (RevenueRefundEvent $refund): int => $refund->excess, $exceeded));
        $hasAnomaly = $unlinkedCount > 0 || $ineligibleCount > 0 || count($exceeded) > 0;

        return [
            'unlinkedCount' => $unlinkedCount,
            'ineligiblePaymentCount' => $ineligibleCount,
            'exceededCount' => count($exceeded),
            'exceededAmount' => $exceededAmount,
            'status' => $hasAnomaly ? 'warning' : 'ok',
        ];
    }

    /** @return array<string, mixed> */
    private function currencyControl(RevenuePeriod $period): array
    {
        $currencies = Payment::query()
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', '!=', self::CURRENCY)
            ->where('confirmed_at', '>=', $period->startUtc())
            ->where('confirmed_at', '<', $period->endExclusiveUtc())
            ->selectRaw('currency, COUNT(*) as payment_count, SUM(amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (Payment $payment): array => [
                'currency' => $payment->currency,
                'paymentCount' => (int) $payment->getAttribute('payment_count'),
                'amount' => (int) $payment->getAttribute('amount'),
            ])
            ->values()
            ->all();

        $paymentCount = (int) array_sum(array_column($currencies, 'paymentCount'));

        return [
            'paymentCount' => $paymentCount,
            'items' => $currencies,
            'status' => $paymentCount > 0 ? 'warning' : 'ok',
        ];
    }
}
