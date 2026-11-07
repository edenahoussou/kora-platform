<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewKoraPointVisitActionRequest;
use App\Http\Requests\StoreKoraPointMemberRequest;
use App\Http\Requests\StoreKoraPointPlvOrderRequest;
use App\Http\Requests\StoreKoraPointVisitRequest;
use App\Http\Requests\UpdateKoraPointPlvOrderRequest;
use App\Http\Requests\UpdateKoraPointVisitActionRequest;
use App\Models\User;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Http\Requests\AssignKoraPointUserRequest;
use App\Modules\KoraPoints\Http\Requests\ChangeKoraPointStatusRequest;
use App\Modules\KoraPoints\Http\Requests\SaveKoraPointRequest;
use App\Modules\KoraPoints\Http\Requests\UpdateKoraPointTermsRequest;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointPlvOrder;
use App\Modules\KoraPoints\Models\KoraPointVisit;
use App\Modules\KoraPoints\Models\KoraPointVisitAction;
use App\Modules\KoraPoints\Models\KoraPointVisitPhoto;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointFieldOperationsService;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use App\Modules\Settings\Services\DatabaseSettingsCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $message = 'KORA Point créé.';
        $accessSummary = $this->newAgentAccessSummary($point);

        if ($accessSummary !== null) {
            $message .= ' '.$accessSummary;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        if ($request->user()->hasRole('kora_point_supervisor')) {
            return to_route('kora-partner.points');
        }

        return to_route('kora-points');
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

    /**
     * Creates an active agent account for this point and starts its web invitation.
     */
    public function storeMember(StoreKoraPointMemberRequest $request, KoraPoint $point): RedirectResponse
    {
        $result = $this->management->createAgent($request->user(), $point, $request->memberAttributes(), $request);
        $invitation = $result['web_access_invitation'];
        $delivery = $invitation?->status === KoraPointWebAccessInvitation::STATUS_SENT
            ? 'Invitation envoyée par '.($invitation->delivery_channel === 'sms' ? 'SMS' : 'e-mail').'.'
            : 'Invitation en attente de livraison.';

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membre ajouté. '.$delivery]);

        return back();
    }

    public function storeVisit(StoreKoraPointVisitRequest $request, KoraPoint $point, KoraPointFieldOperationsService $operations): RedirectResponse
    {
        $operations->createVisit($request->user(), $point, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visite enregistrée.']);

        return back();
    }

    public function realizeVisitAction(UpdateKoraPointVisitActionRequest $request, KoraPointVisit $visit, KoraPointVisitAction $action, KoraPointFieldOperationsService $operations): RedirectResponse
    {
        abort_unless($action->visit_id === $visit->getKey(), 404);
        $operations->realizeAction($request->user(), $action, $request->validated('realization_note'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Réalisation signalée à KORA.']);

        return back();
    }

    public function reviewVisitAction(ReviewKoraPointVisitActionRequest $request, KoraPointVisit $visit, KoraPointVisitAction $action, KoraPointFieldOperationsService $operations): RedirectResponse
    {
        abort_unless($action->visit_id === $visit->getKey(), 404);
        $attributes = $request->validated();
        $operations->reviewAction($request->user(), $action, $attributes['operation'], $attributes['reason'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => $attributes['operation'] === 'validate' ? 'Action validée.' : 'Action renvoyée au responsable.']);

        return back();
    }

    public function storePlvOrder(StoreKoraPointPlvOrderRequest $request, KoraPoint $point, KoraPointFieldOperationsService $operations): RedirectResponse
    {
        $operations->createPlvOrder($request->user(), $point, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commande PLV préparée.']);

        return back();
    }

    public function updatePlvOrder(UpdateKoraPointPlvOrderRequest $request, KoraPointPlvOrder $order, KoraPointFieldOperationsService $operations): RedirectResponse
    {
        $attributes = $request->validated();
        $operations->updatePlvOrder($request->user(), $order, $attributes['operation'], $attributes['reason'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commande PLV mise à jour.']);

        return back();
    }

    public function visitPhoto(Request $request, KoraPointVisit $visit, KoraPointVisitPhoto $photo, KoraPointAccess $access): BinaryFileResponse
    {
        $access->requirePoint($request->user(), $visit->point);
        abort_unless($photo->visit_id === $visit->getKey(), 404);
        $storage = Storage::disk($photo->disk);
        abort_unless($storage->exists($photo->path), 404);

        return response()->file($storage->path($photo->path), [
            'Content-Type' => $photo->mime_type,
            'Content-Disposition' => 'inline; filename="'.rawurlencode($photo->original_name).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function newAgentAccessSummary(KoraPoint $point): ?string
    {
        $agent = User::query()
            ->where('agency_id', $point->agency_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'kora_point_agent'))
            ->with('latestKoraPointWebAccessInvitation')
            ->latest('id')
            ->first();

        $invitation = $agent?->latestKoraPointWebAccessInvitation;

        if ($invitation === null) {
            return null;
        }

        $expiresAt = $invitation->expires_at === null ? null : Carbon::parse($invitation->expires_at);
        $status = $invitation->status === KoraPointWebAccessInvitation::STATUS_SENT
            && $expiresAt?->isPast()
            ? KoraPointWebAccessInvitation::STATUS_EXPIRED
            : $invitation->status;
        $delivery = $status === KoraPointWebAccessInvitation::STATUS_SENT
            ? 'délivré par '.($invitation->delivery_channel === 'sms' ? 'SMS' : 'e-mail')
            : 'en attente de livraison';

        return "Accès web initial {$delivery}. Le mot de passe n’est pas affiché; suivi et renvoi dans Utilisateurs.";
    }
}
