<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\RevenueReportRequest;
use App\Modules\Finance\Services\ConsolidatedRevenueReport;
use App\Modules\Finance\Services\FinanceExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    public function index(RevenueReportRequest $request, ConsolidatedRevenueReport $report): Response
    {
        $period = $request->reportPeriod();

        return Inertia::render('revenue', array_merge(
            $report->build($period),
            [
                'period' => $period->toArray(),
                'periodOptions' => [
                    'this_week' => 'Cette semaine',
                    'this_month' => 'Ce mois',
                    'last_month' => 'Le mois passé',
                    'last_year' => 'L’année passée',
                    'custom' => 'Période personnalisée',
                ],
            ],
        ));
    }

    public function export(
        RevenueReportRequest $request,
        ConsolidatedRevenueReport $report,
        FinanceExportService $exporter,
    ): StreamedResponse {
        $period = $request->reportPeriod();
        $data = $report->build($period);

        activity('finance')
            ->causedBy($request->user())
            ->event('revenue_exported')
            ->withProperties([
                'kind' => 'consolidated_revenue',
                'preset' => $period->preset,
                'from' => $period->localStart->toDateString(),
                'to' => $period->localEnd->toDateString(),
                'timezone' => $period::TIMEZONE,
                'entry_count' => $data['summary']['entryCount'],
            ])
            ->log('finance_revenue_exported');

        return $exporter->workbook(
            'rapport-recettes-'.$period->localStart->format('Ymd').'-'.$period->localEnd->format('Ymd').'.xlsx',
            $report->exportSheets($period, $data),
        );
    }
}
