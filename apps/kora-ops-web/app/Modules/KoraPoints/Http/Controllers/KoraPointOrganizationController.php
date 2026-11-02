<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
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
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $query = KoraPointOrganization::query()->with(['documents', 'users', 'media']);
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

        $organizations = $query->latest('id')->paginate(30)->through(fn (KoraPointOrganization $org): array => $this->organization($org));

        $subpoints = $this->access->points($actor)
            ->whereNotNull('organization_id')
            ->with(['agency', 'organization.media'])
            ->get()
            ->map(function (KoraPoint $point): array {
                $exposure = (int) $point->entries()->sum('amount');
                $limit = (int) ($point->ceiling_amount ?? 0);
                $usage = $limit > 0 ? (int) round(($exposure / $limit) * 100) : 0;
                $status = $point->status === 'suspended' ? 'SUSPENDED' : ($limit > 0 && $exposure >= $limit ? 'LIMIT_REACHED' : ($limit > 0 && $exposure >= ($limit * 0.8) ? 'WARNING_THRESHOLD' : 'ACTIVE'));

                return [
                    'uuid' => $point->uuid,
                    'code' => $point->agency->code,
                    'name' => $point->agency->name,
                    'city' => $point->agency->city,
                    'manager_name' => $point->responsible_name,
                    'phone' => $point->phone,
                    'status' => $status,
                    'daily_sales_amount' => (int) $point->sales()->whereDate('created_at', today())->sum('gross_amount'),
                    'daily_tickets_count' => (int) $point->sales()->whereDate('created_at', today())->count(),
                    'monthly_sales_amount' => (int) $point->sales()->whereMonth('created_at', now()->month)->sum('gross_amount'),
                    'credit_limit' => $limit,
                    'current_exposure' => $exposure,
                    'usage_percentage' => $usage,
                    'commission_rate_bps' => (int) ($point->commission_basis_points ?? $point->organization->commission_basis_points ?? 0),
                    'monthly_commission_earned' => (int) $point->entries()->whereMonth('created_at', now()->month)->sum('commission_delta'),
                    'last_settlement_date' => $point->created_at?->format('d M Y') ?? 'Aucun',
                    'organization_uuid' => $point->organization?->uuid,
                    'organization_name' => $point->organization?->name,
                    'responsible_name' => $point->responsible_name,
                    'address' => $point->address,
                    'country' => $point->agency->country ?? 'BJ',
                    'department' => $point->agency->department,
                    'settlement_calendar' => $point->settlement_calendar ?? $point->organization->settlement_calendar,
                ];
            })->all();

        $unattachedPoints = $this->access->points($actor)
            ->whereNull('organization_id')
            ->with('agency')
            ->get()
            ->map(fn (KoraPoint $point): array => [
                'uuid' => $point->uuid,
                'code' => $point->agency->code,
                'name' => $point->agency->name,
                'city' => $point->agency->city,
                'country' => $point->agency->country ?? 'BJ',
                'department' => $point->agency->department,
                'responsible_name' => $point->responsible_name,
                'phone' => $point->phone,
                'address' => $point->address,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
            ])->all();

        return Inertia::render('kora-points/organizations/index', [
            'organizations' => $organizations,
            'subpoints' => $subpoints,
            'unattached_points' => $unattachedPoints,
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
        $this->access->requireOrganization($request->user(), $organization);

        return Inertia::render('kora-points/organizations/show', [
            'organization' => $this->organization($organization),
            'historical_sales' => $this->access->sales($request->user())->where('organization_id', $organization->getKey())->with(['reservation', 'point'])->latest('id')->paginate(30, ['*'], 'sales_page')->through(fn (KoraPointSale $sale): array => $this->read->sale($sale)),
            'points' => $this->read->query($request->user())->where('organization_id', $organization->getKey())->latest('id')->paginate(30)->through(fn (KoraPoint $point): array => $this->read->point($point, $request->user())),
            'documents' => KoraPartnerDocument::query()->where('organization_id', $organization->getKey())->whereNull('point_id')->latest('id')->get()->map(fn (KoraPartnerDocument $document): array => $this->read->document($document))->all(),
        ]);
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
            'points_count' => $organization->points()->count(),
            'active_points_count' => $organization->points()->where('status', 'active')->count(),
            'total_monthly_sales' => (int) $organization->sales()->sum('gross_amount'),
            'total_exposure' => (int) $organization->points()->withSum('entries as debt_total', 'amount')->get()->sum('debt_total'),
            'allocated_credit_limit' => $this->read->allocatedCreditLimit($organization),
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
            'users' => $organization->users->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'role' => $user->hasRole('kora_point_treasurer') ? 'ORG_TREASURER' : ($user->hasRole('kora_point_supervisor') ? 'ORG_MANAGER' : 'ORG_CONTROLLER'),
                'role_label' => $user->hasRole('kora_point_treasurer') ? 'Trésorier Général' : ($user->hasRole('kora_point_supervisor') ? 'Directeur Commercial & Réseau' : 'Contrôleur'),
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'status' => $user->status === UserStatus::Active ? 'ACTIVE' : 'SUSPENDED',
            ])->all(),
            'documents' => $organization->documents->map(function (KoraPartnerDocument $doc): array {
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
}
