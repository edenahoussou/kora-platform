<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\KoraPoints\Http\Requests\AssignKoraPointUserRequest;
use App\Modules\KoraPoints\Http\Requests\ChangeKoraPointStatusRequest;
use App\Modules\KoraPoints\Http\Requests\SaveKoraPointRequest;
use App\Modules\KoraPoints\Http\Requests\UpdateKoraPointTermsRequest;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use App\Modules\Settings\Services\DatabaseSettingsCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KoraPointController extends Controller
{
    public function __construct(private readonly KoraPointManagementService $management, private readonly KoraPointReadService $read) {}

    /**
     * Persists global default KORA Point terms in the audited settings store.
     */
    public function defaults(UpdateKoraPointTermsRequest $request, DatabaseSettingsCoordinator $settings): RedirectResponse
    {
        $mapping = ['commission_basis_points' => 'koraPointCommissionBasisPoints', 'ceiling_amount' => 'koraPointCeilingAmount', 'settlement_calendar' => 'koraPointSettlementCalendar', 'grace_days' => 'koraPointGraceDays'];
        $values = [];
        foreach ($request->validated() as $key => $value) {
            if (isset($mapping[$key])) {
                $values[$mapping[$key]] = $value;
            }
        }
        $settings->updateKoraPointDefaults($values, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paramètres KORA POINT enregistrés.']);

        return back();
    }

    /**
     * Streams a CSV export of the point sales within the actor visibility scope.
     */
    public function export(Request $request, KoraPoint $point, KoraPointAccess $access): StreamedResponse
    {
        $access->requirePoint($request->user(), $point);
        $sales = $access->sales($request->user())->where('point_id', $point->getKey());

        return response()->streamDownload(function () use ($sales): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Impossible de créer le fichier exporté.');
            }
            fputcsv($stream, ['Vente', 'Statut', 'Brut XOF', 'Commission XOF', 'Encours XOF'], ',', '"', '');
            foreach ($sales->orderBy('id')->lazyById() as $sale) {
                fputcsv($stream, [$sale->uuid, $sale->status, $sale->gross_amount, $sale->commission_amount, $sale->outstanding_amount], ',', '"', '');
            }
            fclose($stream);
        }, 'kora-point-'.$point->uuid.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * Renders the commercial network admin screen.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('kora-points/index', $this->read->index($request->user(), $request->only('q', 'type', 'state')));
    }

    /**
     * Renders the commissions overview reusing the network props.
     */
    public function commissions(Request $request): Response
    {
        return Inertia::render('kora-points/commissions', $this->read->index($request->user(), $request->only('q', 'type', 'state')));
    }

    /**
     * Renders a single point detail workspace.
     */
    public function show(Request $request, KoraPoint $point): Response
    {
        return Inertia::render('kora-points/show', $this->read->show($request->user(), $point));
    }

    /**
     * Creates a KORA Point then redirects to its detail page.
     */
    public function store(SaveKoraPointRequest $request): RedirectResponse
    {
        $point = $this->management->savePoint($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'KORA Point créé.']);

        return to_route('kora-points.show', $point->uuid);
    }

    /**
     * Updates an existing KORA Point.
     */
    public function update(SaveKoraPointRequest $request, KoraPoint $point): RedirectResponse
    {
        $this->management->savePoint($request->user(), $request->validated(), $point);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'KORA Point enregistré.']);

        return back();
    }

    /**
     * Overrides the financial terms of a single point.
     */
    public function terms(UpdateKoraPointTermsRequest $request, KoraPoint $point): RedirectResponse
    {
        $this->management->updateTerms($request->user(), $point, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conditions commerciales enregistrées.']);

        return back();
    }

    /**
     * Applies a lifecycle status change on a point.
     */
    public function status(ChangeKoraPointStatusRequest $request, KoraPoint $point): RedirectResponse
    {
        $this->management->changeStatus($request->user(), $point, $request->validated('status'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut enregistré.']);

        return back();
    }

    /**
     * Assigns an agent user to the point.
     */
    public function assign(AssignKoraPointUserRequest $request, KoraPoint $point): RedirectResponse
    {
        $this->management->assign($request->user(), User::query()->where('uuid', $request->validated('user_uuid'))->firstOrFail(), $point, $request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent affecté au Point.']);

        return back();
    }
}
