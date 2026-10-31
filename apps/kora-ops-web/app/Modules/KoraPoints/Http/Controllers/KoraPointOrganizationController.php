<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\KoraPoints\Http\Requests\AssignKoraPointUserRequest;
use App\Modules\KoraPoints\Http\Requests\ChangeKoraPointStatusRequest;
use App\Modules\KoraPoints\Http\Requests\SaveKoraPointOrganizationRequest;
use App\Modules\KoraPoints\Http\Requests\UpdateKoraPointTermsRequest;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointOrganizationController extends Controller
{
    public function __construct(private readonly KoraPointManagementService $management, private readonly KoraPointAccess $access, private readonly KoraPointReadService $read) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $this->access->authorize($actor, 'kora-points.view');
        $query = KoraPointOrganization::query();
        if ($this->access->isPartner($actor)) {
            $query->whereKey($actor->hasRole('kora_point_agent') ? 0 : ($actor->kora_point_organization_id ?? 0));
        }

        return Inertia::render('kora-points/organizations/index', ['organizations' => $query->latest('id')->paginate(30)->through(fn (KoraPointOrganization $org): array => $this->organization($org))]);
    }

    public function show(Request $request, KoraPointOrganization $organization): Response
    {
        $this->access->requireOrganization($request->user(), $organization);

        return Inertia::render('kora-points/organizations/show', [
            'organization' => $this->organization($organization),
            'historical_sales' => $this->access->sales($request->user())->where('organization_id', $organization->getKey())->with(['reservation', 'point'])->latest('id')->paginate(30, ['*'], 'sales_page')->through(fn (KoraPointSale $sale): array => $this->read->sale($sale)),
            'points' => $this->read->query($request->user())->where('organization_id', $organization->getKey())->latest('id')->paginate(30)->through(fn (KoraPoint $point): array => $this->read->point($point, $request->user())),
            'documents' => KoraPartnerDocument::query()->where('organization_id', $organization->getKey())->whereNull('point_id')->latest('id')->get()->map(fn (KoraPartnerDocument $document): array => $this->read->document($document))->all(),
        ]);
    }

    public function store(SaveKoraPointOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->management->saveOrganization($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation partenaire créée.']);

        return to_route('kora-points.organizations.show', $organization->uuid);
    }

    public function update(SaveKoraPointOrganizationRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->saveOrganization($request->user(), $request->validated(), $organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation enregistrée.']);

        return back();
    }

    public function terms(UpdateKoraPointTermsRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->updateTerms($request->user(), $organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conditions commerciales enregistrées.']);

        return back();
    }

    public function status(ChangeKoraPointStatusRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->changeStatus($request->user(), $organization, $request->validated('status'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut enregistré.']);

        return back();
    }

    public function assign(AssignKoraPointUserRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->assign($request->user(), User::query()->where('uuid', $request->validated('user_uuid'))->firstOrFail(), $organization, $request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur affecté à l’organisation.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function organization(KoraPointOrganization $organization): array
    {
        return ['uuid' => $organization->uuid, 'code' => $organization->code, 'name' => $organization->name,
            'status' => $organization->status, 'phone' => $organization->phone, 'address' => $organization->address,
            'commission_basis_points' => $organization->commission_basis_points, 'ceiling_amount' => $organization->ceiling_amount,
            'settlement_calendar' => $organization->settlement_calendar];
    }
}
