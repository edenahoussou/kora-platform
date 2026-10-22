<?php

namespace App\Modules\Profitability\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use App\Modules\Profitability\Http\Requests\ProfitabilityReportRequest;
use App\Modules\Profitability\Services\ProfitabilityExport;
use App\Modules\Profitability\Services\ProfitabilityReport;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitabilityController extends Controller
{
    /** @var array<string, string> */
    private const PERIOD_OPTIONS = [
        'this_month' => 'Ce mois',
        'last_month' => 'Le mois passé',
        'this_quarter' => 'Trimestre courant',
        'last_quarter' => 'Trimestre précédent',
        'last_year' => 'L’année passée',
        'custom' => 'Période personnalisée',
    ];

    public function index(ProfitabilityReportRequest $request, ProfitabilityReport $report): Response
    {
        $period = $request->reportPeriod();
        $axis = $request->axis();
        $scope = $request->entityScope();
        $data = $report->build($period, $axis, $scope);
        $detailKey = $request->detailKey();

        return Inertia::render('profitability', array_merge($data, [
            'period' => $period->toArray(),
            'periodOptions' => self::PERIOD_OPTIONS,
            'axisOptions' => collect(ProfitabilityAxis::cases())
                ->mapWithKeys(fn (ProfitabilityAxis $option): array => [$option->value => $option->label()])
                ->all(),
            'detail' => $detailKey === null ? null : $report->detail($period, $axis, $detailKey, $scope),
        ]));
    }

    public function export(
        ProfitabilityReportRequest $request,
        ProfitabilityReport $report,
        ProfitabilityExport $exporter,
    ): StreamedResponse {
        $period = $request->reportPeriod();
        $axis = $request->axis();
        $scope = $request->entityScope();
        $data = $report->build($period, $axis, $scope);

        activity('finance')
            ->causedBy($request->user())
            ->event('profitability_exported')
            ->withProperties([
                'kind' => 'profitability',
                'axis' => $axis->value,
                'preset' => $period->preset,
                'from' => $period->localStart->toDateString(),
                'to' => $period->localEnd->toDateString(),
                'timezone' => RevenuePeriod::TIMEZONE,
                'entity_type' => $scope?->type->value,
                'entity' => $scope?->uuid,
                'card_count' => count($data['cards']),
            ])
            ->log('finance_profitability_exported');

        return $exporter->download($period, $axis, $data, $scope);
    }
}
