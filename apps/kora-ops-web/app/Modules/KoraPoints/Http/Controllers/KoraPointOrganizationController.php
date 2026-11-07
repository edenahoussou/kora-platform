<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKoraPointOrganizationMemberRequest;
use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Http\Requests\AssignKoraPointUserRequest;
use App\Modules\KoraPoints\Http\Requests\ChangeKoraPointStatusRequest;
use App\Modules\KoraPoints\Http\Requests\SaveKoraPointOrganizationRequest;
use App\Modules\KoraPoints\Http\Requests\UpdateKoraPointTermsRequest;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Models\KoraPointVisit;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointOrganizationController extends Controller
{
    public function __construct(private readonly KoraPointManagementService $management, private readonly KoraPointAccess $access, private readonly KoraPointReadService $read, private readonly KoraPointTermsService $terms) {}

    /**
     * Renders the organizations screen with contractual matrix, sub-points, users and documents.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $this->access->authorize($actor, 'kora-points.view');
        $query = KoraPointOrganization::query()->with(['documents.media', 'users.roles', 'media']);
        if ($this->access->isPartner($actor)) {
            $query->whereKey($actor->hasRole('kora_point_agent') ? 0 : ($actor->kora_point_organization_id ?? 0));
        }

        $search = (string) $request->input('q', '');
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
            });
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['active', 'suspended', 'closed'], true)) {
            $query->where('status', $status);
        }

        $indexSummary = $this->organizationIndexSummary(clone $query);
        $organizations = $query->latest('id')->paginate(30)->withQueryString()->through(fn (KoraPointOrganization $org): array => $this->organization($org));

        return Inertia::render('kora-points/organizations/index', [
            'organizations' => $organizations,
            'index_summary' => $indexSummary,
            'permissions' => [
                'canManage' => $actor->can('kora-points.manage'),
                'canManageTerms' => $actor->can('kora-points.terms'),
            ],
        ]);
    }

    /**
     * Renders a single organization detail with sales, points and documents.
     */
    public function show(Request $request, KoraPointOrganization $organization): Response
    {
        $actor = $request->user();
        $this->access->authorizeInternal($actor, 'kora-points.view');

        $organization->loadMissing(['media', 'documents.media']);
        $pointSaleIds = $organization->sales()->select('id');
        $sales = $organization->sales()
            ->with(['reservation', 'point.agency'])
            ->latest('id')
            ->paginate(30, ['*'], 'sales_page')
            ->withQueryString()
            ->through(fn (KoraPointSale $sale): array => $this->read->sale($sale) + [
                'point_name' => $sale->point->agency->name,
                'point_code' => $sale->point->agency->code,
            ]);

        $points = $this->read->query($actor)
            ->where('organization_id', $organization->getKey())
            ->latest('id')
            ->paginate(30, ['*'], 'points_page')
            ->withQueryString()
            ->through(fn (KoraPoint $point): array => $this->read->point($point, $actor));

        $refundPage = KoraPointRefund::query()
            ->whereIn('sale_id', $pointSaleIds)
            ->with(['sale.point.agency'])
            ->latest('id')
            ->paginate(30, ['*'], 'refunds_page')
            ->withQueryString();
        $refundCorrections = KoraPointEntry::query()
            ->whereIn('event_key', $refundPage->getCollection()->map(fn (KoraPointRefund $refund): string => 'refund:'.$refund->uuid))
            ->get(['event_key', 'commission_delta'])
            ->keyBy('event_key');
        $refunds = $refundPage->through(fn (KoraPointRefund $refund): array => [
            'uuid' => $refund->uuid,
            'sale_uuid' => $refund->sale->uuid,
            'point_name' => $refund->sale->point->agency->name,
            'status' => $refund->status,
            'amount' => (int) ($refund->amount ?? 0),
            'commission_reversed' => abs((int) ($refundCorrections->get('refund:'.$refund->uuid)->commission_delta ?? 0)),
            'reason' => $refund->reason,
            'executed_at' => $refund->executed_at?->toIso8601String(),
        ]);

        $remittancePage = KoraPointRemittance::query()
            ->where(fn ($query) => $query
                ->where('organization_id', $organization->getKey())
                ->orWhereHas('lines.sale.point', fn ($pointsQuery) => $pointsQuery->where('organization_id', $organization->getKey())))
            ->with(['lines.sale.point.agency'])
            ->latest('id')
            ->paginate(30, ['*'], 'remittances_page')
            ->withQueryString();
        $remittances = $remittancePage->through(function (KoraPointRemittance $remittance) use ($organization): array {
            $pointAmounts = $remittance->lines
                ->filter(fn ($line): bool => $line->sale?->point?->organization_id === $organization->getKey())
                ->groupBy(fn ($line): string => $line->sale->point->uuid)
                ->map(function ($lines): array {
                    $point = $lines->first()->sale->point;

                    return [
                        'uuid' => $point->uuid,
                        'name' => $point->agency->name,
                        'amount' => (int) $lines->sum('amount'),
                    ];
                })->values();

            return [
                'uuid' => $remittance->uuid,
                'reference' => $remittance->reference ?: 'REV-'.strtoupper(substr($remittance->uuid, 0, 8)),
                'status' => $remittance->status,
                'group_amount' => (int) $remittance->amount,
                'received_amount' => $remittance->received_amount,
                'submitted_at' => $remittance->submitted_at?->toIso8601String(),
                'validated_at' => $remittance->validated_at?->toIso8601String(),
                'points' => $pointAmounts->all(),
                'url' => route('kora-points.remittances.show', $remittance->uuid),
            ];
        });

        $members = $organization->users()
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['kora_point_supervisor', 'kora_point_treasurer']))
            ->with('roles')
            ->orderBy('name')
            ->paginate(30, ['uuid', 'name', 'email', 'phone', 'status'], 'members_page')
            ->withQueryString()
            ->through(fn (User $member): array => [
                'uuid' => $member->uuid,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'status' => $member->status->value,
                'role' => $member->hasRole('kora_point_supervisor') ? 'kora_point_supervisor' : 'kora_point_treasurer',
                'role_label' => $member->hasRole('kora_point_supervisor') ? 'Superviseur' : 'Trésorier',
            ]);

        $organizationDocuments = KoraPartnerDocument::query()
            ->where('organization_id', $organization->getKey())
            ->whereNull('point_id')
            ->whereNull('remittance_id')
            ->whereNull('refund_id')
            ->with('media')
            ->latest('id')
            ->paginate(30, ['*'], 'documents_page')
            ->withQueryString()
            ->through(fn (KoraPartnerDocument $document): array => $this->read->document($document));

        $visits = KoraPointVisit::query()
            ->whereHas('point', fn (Builder $pointsQuery) => $pointsQuery->where('organization_id', $organization->getKey()))
            ->with(['point.agency', 'creator', 'actions.assignee', 'actions.reviewer', 'photos'])
            ->latest('id')
            ->paginate(30, ['*'], 'visits_page')
            ->withQueryString()
            ->through(fn (KoraPointVisit $visit): array => $this->read->visit($visit, $actor) + [
                'point_uuid' => $visit->point->uuid,
                'point_name' => $visit->point->agency->name,
            ]);

        $serializedOrganization = $this->organization($organization);
        $allocated = (int) $serializedOrganization['allocated_credit_limit'];
        $ceiling = (int) $serializedOrganization['credit_limit'];

        return Inertia::render('kora-points/organizations/show', [
            'organization' => $serializedOrganization,
            'summary' => [
                'global_ceiling_amount' => $ceiling,
                'allocated_amount' => $allocated,
                'available_amount' => max(0, $ceiling - $allocated),
                'exposure_amount' => (int) $serializedOrganization['total_exposure'],
            ],
            'points' => $points,
            'sales' => $sales,
            'refunds' => $refunds,
            'remittances' => $remittances,
            'visits' => $visits,
            'members' => $members,
            'documents' => $organizationDocuments,
            'unattached_points' => $actor->can('kora-points.manage')
                ? $this->access->points($actor)->whereNull('organization_id')->with('agency')->get()->filter(fn (KoraPoint $point): bool => $point->agency->country === $organization->country)->map(fn (KoraPoint $point): array => [
                    'uuid' => $point->uuid,
                    'code' => $point->agency->code,
                    'name' => $point->agency->name,
                    'city' => $point->agency->city,
                ])->values()->all()
                : [],
            'permissions' => [
                'canManage' => $actor->can('kora-points.manage'),
                'canManageTerms' => $actor->can('kora-points.terms'),
                'canManageMembers' => $actor->status === UserStatus::Active && $actor->can('users.manage') && $actor->can('kora-points.manage'),
            ],
        ]);
    }

    /** Creates a supervisor or treasurer account and sends its organization invitation. */
    public function storeMember(StoreKoraPointOrganizationMemberRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $validated = $request->memberAttributes();
        $this->management->createOrganizationMember($request->user(), $organization, $validated, $request);

        $message = 'Membre ajouté et invitation lancée.';
        $accessSummary = $this->newMemberAccessSummary([['email' => $validated['email']]]);

        if ($accessSummary !== null) {
            $message .= ' '.$accessSummary;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Creates a partner organization then redirects to its detail page.
     */
    public function store(SaveKoraPointOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->management->saveOrganization($request->user(), $request->validated());
        $message = 'Organisation partenaire créée.';
        $accessSummary = $this->newMemberAccessSummary($request->validated('members', []));

        if ($accessSummary !== null) {
            $message .= ' '.$accessSummary;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('kora-points.organizations.index');
    }

    /**
     * Updates an existing partner organization.
     */
    public function update(SaveKoraPointOrganizationRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->saveOrganization($request->user(), $request->validated(), $organization);

        $message = 'Organisation enregistrée.';
        $accessSummary = $this->newMemberAccessSummary($request->validated('members', []));

        if ($accessSummary !== null) {
            $message .= ' '.$accessSummary;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Updates the framework financial terms of an organization.
     */
    public function terms(UpdateKoraPointTermsRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->updateTerms($request->user(), $organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conditions commerciales enregistrées.']);

        return back();
    }

    /**
     * Applies a lifecycle status change on an organization.
     */
    public function status(ChangeKoraPointStatusRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->changeStatus($request->user(), $organization, $request->validated('status'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut enregistré.']);

        return back();
    }

    /** @param array<int, array{name?: string, email?: string}> $members */
    private function newMemberAccessSummary(array $members): ?string
    {
        $emails = collect($members)->pluck('email')->filter()->map(fn (string $email): string => strtolower(trim($email)))->all();

        if ($emails === []) {
            return null;
        }

        $invitations = User::query()
            ->whereIn('email', $emails)
            ->with('latestKoraPointWebAccessInvitation')
            ->get()
            ->map(fn (User $user) => $user->latestKoraPointWebAccessInvitation)
            ->filter();

        if ($invitations->isEmpty()) {
            return null;
        }

        $sent = $invitations->filter(fn ($invitation): bool => $invitation->status === 'sent'
            && $invitation->expires_at !== null
            && Carbon::parse($invitation->expires_at)->isFuture())->count();
        $pending = $invitations->filter(fn ($invitation): bool => $invitation->status === 'pending_delivery')->count();

        return "Accès web initial : {$sent} délivré(s), {$pending} en attente. Le mot de passe n’est pas affiché; suivi et renvoi dans Utilisateurs.";
    }

    /**
     * Assigns a supervisor or treasurer user to the organization.
     */
    public function assign(AssignKoraPointUserRequest $request, KoraPointOrganization $organization): RedirectResponse
    {
        $this->management->assign($request->user(), User::query()->where('uuid', $request->validated('user_uuid'))->firstOrFail(), $organization, $request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur affecté à l’organisation.']);

        return back();
    }

    /** @return array<string, mixed> */
    /**
     * Maps an organization to its public representation including terms, users and documents.
     *
     * @return array<string, mixed>
     */
    private function organization(KoraPointOrganization $organization): array
    {
        $pointFinancials = $organization->points()
            ->withSum('entries as ledger_balance', 'amount')
            ->get(['id', 'status', 'ceiling_amount']);
        $totalExposure = (int) $pointFinancials->sum(fn (KoraPoint $point): int => (int) ($point->getAttribute('ledger_balance') ?? 0));
        $allocatedCreditLimit = (int) $pointFinancials->sum(function (KoraPoint $point): int {
            $balance = (int) ($point->getAttribute('ledger_balance') ?? 0);

            return $point->status === 'closed' && $balance === 0
                ? 0
                : max(0, (int) ($point->ceiling_amount ?? 0));
        });

        return [
            'uuid' => $organization->uuid,
            'code' => $organization->code,
            'name' => $organization->name,
            'logo_url' => $organization->getFirstMediaUrl('logo', 'thumb') ?: null,
            'legal_name' => $organization->name,
            'headquarters_city' => null,
            'country' => $organization->country,
            'registration_identifier' => $organization->registration_identifier,
            'tax_identifier' => $organization->tax_identifier,
            'status' => $organization->status,
            'phone' => $organization->phone,
            'address' => $organization->address,
            'commission_basis_points' => $organization->commission_basis_points,
            'ceiling_amount' => $organization->ceiling_amount,
            'settlement_calendar' => $organization->settlement_calendar,
            'points_count' => $pointFinancials->count(),
            'active_points_count' => $pointFinancials->where('status', 'active')->count(),
            'total_cumulative_sales' => (int) $organization->sales()->sum('gross_amount'),
            'total_monthly_sales' => (int) $organization->sales()->sum('gross_amount'),
            'total_exposure' => $totalExposure,
            'allocated_credit_limit' => $allocatedCreditLimit,
            'can_close' => $pointFinancials->every(fn (KoraPoint $point): bool => (int) ($point->getAttribute('ledger_balance') ?? 0) === 0),
            'credit_limit' => (int) $organization->ceiling_amount,
            'default_terms' => [
                'commission_rate_bps' => (int) $organization->commission_basis_points,
                'credit_limit' => (int) $organization->ceiling_amount,
                'settlement_frequency_days' => match ($organization->settlement_calendar['frequency'] ?? 'daily') {
                    'monthly' => 30,
                    'weekly' => 7,
                    default => 1,
                },
                'grace_period_days' => $this->terms->graceDays(),
            ],
            'users' => $organization->users->filter(fn (User $user): bool => $user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer']))->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'role' => $user->hasRole('kora_point_treasurer') ? 'ORG_TREASURER' : ($user->hasRole('kora_point_supervisor') ? 'ORG_MANAGER' : 'ORG_CONTROLLER'),
                'role_label' => $user->hasRole('kora_point_treasurer') ? 'Trésorier Général' : ($user->hasRole('kora_point_supervisor') ? 'Directeur Commercial & Réseau' : 'Contrôleur'),
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'status' => $user->status === UserStatus::Active ? 'ACTIVE' : 'SUSPENDED',
            ])->all(),
            'documents' => $organization->documents->whereNull('point_id')->map(function (KoraPartnerDocument $doc): array {
                $size = $doc->getFirstMedia('proof')?->size;

                return [
                    'uuid' => $doc->uuid,
                    'name' => $doc->title,
                    'type' => strtoupper((string) $doc->kind),
                    'uploaded_at' => $doc->created_at?->format('d M Y') ?? '',
                    'size' => $size === null ? null : number_format($size / 1024, 1, ',', ' ').' Ko',
                    'url' => route('kora-points.documents.download', $doc->uuid),
                ];
            })->all(),
        ];
    }

    /**
     * @param  Builder<KoraPointOrganization>  $organizationsQuery
     * @return array{organizations: int, active_points: int, exposure: int, available_allocation: int}
     */
    private function organizationIndexSummary(Builder $organizationsQuery): array
    {
        $matchedOrganizations = (clone $organizationsQuery)
            ->setEagerLoads([])
            ->get(['id', 'ceiling_amount']);

        if ($matchedOrganizations->isEmpty()) {
            return ['organizations' => 0, 'active_points' => 0, 'exposure' => 0, 'available_allocation' => 0];
        }

        $organizationIds = $matchedOrganizations->modelKeys();
        $perPointMetrics = DB::table('kora_points as points')
            ->leftJoin('kora_point_entries as entries', 'entries.point_id', '=', 'points.id')
            ->whereIn('points.organization_id', $organizationIds)
            ->groupBy('points.id', 'points.organization_id', 'points.status', 'points.ceiling_amount')
            ->selectRaw(
                'points.organization_id, 1 AS point_count, CASE WHEN points.status = ? THEN 1 ELSE 0 END AS active_points, COALESCE(SUM(entries.amount), 0) AS exposure, CASE WHEN points.status = ? AND COALESCE(SUM(entries.amount), 0) = 0 THEN 0 ELSE COALESCE(points.ceiling_amount, 0) END AS reserved_allocation',
                ['active', 'closed'],
            );
        $pointMetrics = DB::query()
            ->fromSub($perPointMetrics, 'point_metrics')
            ->selectRaw('organization_id, SUM(point_count) AS point_count, SUM(active_points) AS active_points, SUM(exposure) AS exposure, SUM(reserved_allocation) AS reserved_allocation')
            ->groupBy('organization_id')
            ->get()
            ->keyBy('organization_id');

        $activePoints = 0;
        $exposure = 0;
        $availableAllocation = 0;

        foreach ($matchedOrganizations as $organization) {
            $metrics = $pointMetrics->get($organization->getKey());
            $activePoints += (int) ($metrics->active_points ?? 0);
            $exposure += (int) ($metrics->exposure ?? 0);
            $availableAllocation += max(0, (int) ($organization->ceiling_amount ?? 0) - (int) ($metrics->reserved_allocation ?? 0));
        }

        return [
            'organizations' => $matchedOrganizations->count(),
            'active_points' => $activePoints,
            'exposure' => $exposure,
            'available_allocation' => $availableAllocation,
        ];
    }
}
