<?php

namespace App\Http\Controllers;

use App\Modules\CompanyAccounts\Actions\ResolveReferentCompanyAccountAction;
use App\Modules\Dashboard\Http\Requests\DashboardRequest;
use App\Modules\Dashboard\Services\DashboardReadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        DashboardRequest $request,
        DashboardReadService $dashboard,
        ResolveReferentCompanyAccountAction $referentAccount,
    ): Response|RedirectResponse {
        // Un référent d'organisation sans rôle interne n'a rien à faire du
        // dashboard OPS : il est renvoyé vers son espace entreprise.
        if ($referentAccount->isPortalOnlyUser($request->user())) {
            return to_route('company-portal.index');
        }

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
