<?php

namespace App\Http\Controllers;

use App\Modules\Dashboard\Http\Requests\DashboardRequest;
use App\Modules\Dashboard\Services\DashboardReadService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request, DashboardReadService $dashboard): Response
    {
        $period = $request->dashboardPeriod();

        return Inertia::render('dashboard', array_merge(
            $dashboard->build($request->user(), $period),
            [
                'period' => $period->toArray(),
                'periodOptions' => [
                    'today' => "Aujourd'hui",
                    'this_week' => 'Cette semaine',
                    'this_month' => 'Ce mois',
                    'custom' => 'Période personnalisée',
                ],
            ],
        ));
    }
}
