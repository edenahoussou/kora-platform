<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointPlvOrder;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Models\KoraPointVisit;
use Illuminate\Database\Eloquent\Builder;

class KoraPointReadService
{
    public function __construct(private readonly KoraPointAccess $access, private readonly KoraPointTermsService $terms) {}

    /** @return array<string, mixed> */
    /**
     * Builds the Inertia props for the network admin screen (points, organizations, defaults).
     *
     * @param  array{q?: string, type?: string, state?: string}  $filters
     * @return array<string, mixed>
     */
    public function index(User $actor, array $filters = []): array
    {
        $this->access->authorize($actor, 'kora-points.view');

        $organizationsQuery = KoraPointOrganization::query()->with('media');

        if ($this->access->isPartner($actor)) {
            $organizationsQuery->whereKey(
                $actor->hasRole('kora_point_agent')
                    ? 0
                    : ($actor->kora_point_organization_id ?? 0),
            );
        }

        $organizations = $organizationsQuery
            ->latest('id')
            ->get()
            ->map(fn (KoraPointOrganization $org): array => [
                'uuid' => $org->uuid,
                'code' => $org->code,
                'name' => $org->name,
                'logo_url' => $org->getFirstMediaUrl('logo', 'thumb') ?: null,
                'legal_name' => $org->name,
                'headquarters_city' => null,
                'country' => $org->country,
                'registration_identifier' => $org->registration_identifier,
                'tax_identifier' => $org->tax_identifier,
                'status' => $org->status,
                'phone' => $org->phone,
                'address' => $org->address,
                'points_count' => $org->points()->count(),
                'active_points_count' => $org->points()->where('status', 'active')->count(),
                'total_cumulative_sales' => (int) $org->sales()->sum('gross_amount'),
                'total_monthly_sales' => (int) $org->sales()->sum('gross_amount'),
                'total_exposure' => (int) $org->points()->withSum('entries as debt_total', 'amount')->get()->sum('debt_total'),
                'allocated_credit_limit' => $this->allocatedCreditLimit($org),
                'credit_limit' => (int) $org->ceiling_amount,
                'commission_basis_points' => $org->commission_basis_points,
                'ceiling_amount' => $org->ceiling_amount,
                'settlement_calendar' => $org->settlement_calendar,
                'default_terms' => [
                    'commission_rate_bps' => (int) $org->commission_basis_points,
                    'credit_limit' => (int) $org->ceiling_amount,
                    'settlement_frequency_days' => match ($org->settlement_calendar['frequency'] ?? 'daily') {
                        'monthly' => 30,
                        'weekly' => 7,
                        default => 1,
                    },
                    'grace_period_days' => $this->terms->graceDays(),
                ],
                'users' => [],
                'documents' => [],
            ])->all();

        $pointsQuery = $this->query($actor)->latest('id');
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $pointsQuery->whereHas('agency', fn ($agency) => $agency->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(code) LIKE ?', [$like])->orWhereRaw('LOWER(city) LIKE ?', [$like]));
        }

        if (($filters['type'] ?? '') === 'AUTONOMOUS') {
            $pointsQuery->whereNull('organization_id');
        }

        if (($filters['type'] ?? '') === 'ORGANIZATION_ATTACHED') {
            $pointsQuery->whereNotNull('organization_id');
        }

        if (in_array($filters['state'] ?? '', ['active', 'suspended', 'closed'], true)) {
            $pointsQuery->where('status', $filters['state']);
        }

        return [
            'points' => $pointsQuery->paginate(30)->withQueryString()->through(fn (KoraPoint $point): array => $this->point($point, $actor)),
            'organizations' => $organizations,
            'defaults' => $actor->can('kora-points.terms') ? ($this->terms->defaults() + ['grace_days' => $this->terms->graceDays()]) : null,
            'permissions' => [
                'canManage' => $actor->can('kora-points.manage'),
                'canManageTerms' => $actor->can('kora-points.terms'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    /**
     * Builds the Inertia props for a single point detail (sales, ledger entries, refunds, documents).
     *
     * @return array<string, mixed>
     */
    public function show(User $actor, KoraPoint $point): array
    {
        $this->access->requirePoint($actor, $point);

        $point = $this->query($actor)->whereKey($point->getKey())->firstOrFail();
        $pointSaleIds = $this->access->sales($actor)
            ->where('point_id', $point->getKey())
            ->select('id');
        $organization = $point->organization;
        $canViewNetworkContext = ! $actor->hasRole('kora_point_agent');
        $members = User::query()
            ->when(! $canViewNetworkContext, fn ($users) => $users->whereRaw('1 = 0'))
            ->where('agency_id', $point->agency_id)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'kora_point_agent'))
            ->orderBy('name')
            ->paginate(30, ['uuid', 'name', 'email', 'phone', 'status'], 'members_page')
            ->through(fn (User $member): array => [
                'uuid' => $member->uuid,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'status' => $member->status->value,
            ]);
        $remittances = KoraPointRemittance::query()
            ->whereHas('lines', fn ($lines) => $lines->whereIn('sale_id', $pointSaleIds))
            ->withSum(['lines as point_amount' => fn ($lines) => $lines->whereIn('sale_id', $pointSaleIds)], 'amount')
            ->withCount(['lines as point_sales_count' => fn ($lines) => $lines->whereIn('sale_id', $pointSaleIds)])
            ->withCount('lines as group_sales_count')
            ->latest('id')
            ->paginate(30, ['*'], 'remittances_page')
            ->through(fn (KoraPointRemittance $remittance): array => [
                'uuid' => $remittance->uuid,
                'reference' => $remittance->reference ?: 'REV-'.strtoupper(substr($remittance->uuid, 0, 8)),
                'status' => $remittance->status,
                'point_amount' => (int) $remittance->getAttribute('point_amount'),
                'group_amount' => $canViewNetworkContext ? (int) $remittance->amount : null,
                'received_amount' => $canViewNetworkContext ? $remittance->received_amount : null,
                'sales_count' => (int) $remittance->getAttribute($canViewNetworkContext ? 'group_sales_count' : 'point_sales_count'),
                'submitted_at' => $remittance->submitted_at?->toIso8601String(),
                'validated_at' => $remittance->validated_at?->toIso8601String(),
                'url' => $canViewNetworkContext ? route('kora-points.remittances.show', $remittance->uuid) : null,
            ]);
        $refundPage = KoraPointRefund::query()
            ->whereIn('sale_id', $pointSaleIds)
            ->with('sale')
            ->latest('id')
            ->paginate(30, ['*'], 'refunds_page');
        $refundCommissionCorrections = KoraPointEntry::query()
            ->whereIn('event_key', $refundPage->getCollection()->map(fn (KoraPointRefund $refund): string => 'refund:'.$refund->uuid))
            ->get(['event_key', 'commission_delta'])
            ->keyBy('event_key');
        $refunds = $refundPage->through(fn (KoraPointRefund $refund): array => [
            'uuid' => $refund->uuid,
            'sale_uuid' => $refund->sale->uuid,
            'status' => $refund->status,
            'amount' => $refund->amount,
            'commission_reversed' => abs((int) ($refundCommissionCorrections->get('refund:'.$refund->uuid)->commission_delta ?? 0)),
            'executor' => $refund->executor,
            'reason' => $refund->reason,
            'executed_at' => $refund->executed_at?->toIso8601String(),
        ]);
        $visits = KoraPointVisit::query()
            ->where('point_id', $point->getKey())
            ->with(['creator', 'actions.assignee', 'actions.reviewer', 'photos'])
            ->latest('id')
            ->paginate(30, ['*'], 'visits_page')
            ->withQueryString()
            ->through(fn (KoraPointVisit $visit): array => $this->visit($visit, $actor));
        $plvOrders = KoraPointPlvOrder::query()
            ->where('point_id', $point->getKey())
            ->with(['point.agency', 'creator', 'canceller'])
            ->latest('id')
            ->paginate(30, ['*'], 'plv_page')
            ->withQueryString()
            ->through(fn (KoraPointPlvOrder $order): array => $this->plvOrder($order));
        $visitAssignees = [];
        if (! $this->access->isPartner($actor) && $actor->can('kora-points.manage')) {
            $agentUsers = User::query()->where('status', UserStatus::Active->value)
                ->where('agency_id', $point->agency_id)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'kora_point_agent'))
                ->get(['id', 'uuid', 'name']);
            $internalUsers = User::permission('kora-points.manage')
                ->where('status', UserStatus::Active->value)
                ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['kora_point_agent', 'kora_point_supervisor', 'kora_point_treasurer']))
                ->get(['id', 'uuid', 'name']);
            $visitAssignees = $internalUsers->concat($agentUsers)->unique('id')->map(fn (User $assignee): array => [
                'uuid' => $assignee->uuid,
                'name' => $assignee->name,
                'type' => $assignee->hasRole('kora_point_agent') ? 'Agent du Point' : 'Direction KORA',
            ])->values()->all();
        }

        return [
            'point' => $this->point($point, $actor),
            'organization' => $organization === null ? null : [
                'uuid' => $organization->uuid,
                'code' => $organization->code,
                'name' => $organization->name,
                'country' => $organization->country,
                'status' => $organization->status,
                'global_ceiling_amount' => $canViewNetworkContext ? (int) $organization->ceiling_amount : null,
                'allocated_ceiling_amount' => $canViewNetworkContext ? $this->allocatedCreditLimit($organization) : null,
                'point_allocation_amount' => (int) ($point->ceiling_amount ?? 0),
            ],
            'members' => $members,
            'sales' => $this->access->sales($actor)->where('point_id', $point->getKey())->with(['reservation', 'point'])->latest('id')->paginate(30)->through(fn (KoraPointSale $sale): array => $this->sale($sale)),
            'entries' => KoraPointEntry::query()->whereIn('sale_id', $pointSaleIds)->latest('id')->paginate(30, ['*'], 'entries_page')->through(fn (KoraPointEntry $entry): array => ['uuid' => $entry->uuid, 'type' => $entry->type, 'event' => $entry->event_key, 'amount' => $entry->amount, 'commission_delta' => $entry->commission_delta, 'recorded_at' => $entry->created_at?->toIso8601String()]),
            'refunds' => $refunds,
            'remittances' => $remittances,
            'visits' => $visits,
            'plv_orders' => $plvOrders,
            'visit_assignees' => $visitAssignees,
            'documents' => $this->documents($actor, $point)->latest('id')->get()->map(fn (KoraPartnerDocument $document): array => $this->document($document) + [
                'source' => $document->point_id === $point->getKey() ? 'point' : 'organization',
            ])->all(),
            'organization_options' => $actor->can('kora-points.manage')
                ? KoraPointOrganization::query()
                    ->where(fn ($query) => $query->where('status', 'active')->when($point->organization_id !== null, fn ($query) => $query->orWhereKey($point->organization_id)))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (KoraPointOrganization $option): array => [
                        'uuid' => $option->uuid,
                        'code' => $option->code,
                        'name' => $option->name,
                        'country' => $option->country,
                        'status' => $option->status,
                        'default_terms' => [
                            'commission_rate_bps' => (int) $option->commission_basis_points,
                            'credit_limit' => (int) $option->ceiling_amount,
                            'settlement_frequency_days' => match ($option->settlement_calendar['frequency'] ?? 'daily') {
                                'monthly' => 30,
                                'weekly' => 7,
                                default => 1,
                            },
                            'grace_period_days' => $this->terms->graceDays(),
                        ],
                    ])->all()
                : [],
            'permissions' => [
                'canManage' => $actor->can('kora-points.manage'),
                'canManageTerms' => $actor->can('kora-points.terms') && $point->organization_id === null,
                'canViewNetworkContext' => $canViewNetworkContext,
                'canManageMembers' => ! $this->access->isPartner($actor)
                    && $actor->status === UserStatus::Active
                    && $actor->can('users.manage')
                    && $actor->can('kora-points.manage'),
                'canManageFieldOperations' => ! $this->access->isPartner($actor)
                    && $actor->status === UserStatus::Active
                    && $actor->can('kora-points.manage'),
            ],
        ];
    }

    /** @return Builder<KoraPoint> */
    /**
     * Returns the base points query with aggregated sales, debt and overdue metrics.
     *
     * @return Builder<KoraPoint>
     */
    public function query(User $actor): Builder
    {
        $saleIds = $this->access->sales($actor)->select('id');
        $sales = fn ($query) => $query->whereIn('id', $saleIds);
        $entries = fn ($query) => $query->whereIn('sale_id', $saleIds);
        $due = now()->subDays($this->terms->graceDays());

        return $this->access->points($actor)->with(['agency', 'organization.media', 'media'])
            ->withCount(['sales' => $sales])
            ->withSum(['sales as gross_total' => $sales], 'gross_amount')
            ->withSum(['sales as refunded_total' => $sales], 'refunded_amount')
            ->withSum(['entries as debt_total' => $entries], 'amount')
            ->withSum(['entries as commission_total' => $entries], 'commission_delta')
            ->withExists(['sales as overdue' => fn ($query) => $query->whereIn('id', $saleIds)->where('outstanding_amount', '>', 0)->where('due_at', '<=', $due)]);
    }

    /** @return array<string, mixed> */
    /**
     * Maps a point model to the admin network view shape (status, exposure, terms).
     *
     * @return array<string, mixed>
     */
    public function point(KoraPoint $point, User $actor): array
    {
        $terms = $this->terms->resolve($point);
        $exposure = (int) $point->getAttribute('debt_total');
        $limit = (int) ($terms['ceiling_amount'] ?? 0);
        $usage = $limit > 0 ? (int) round(($exposure / $limit) * 100) : 0;
        $calendar = $terms['settlement_calendar'] ?? [];
        $freqDays = ($calendar['frequency'] ?? 'weekly') === 'monthly' ? 30 : (($calendar['frequency'] ?? 'weekly') === 'daily' ? 1 : 7);

        $status = match ($point->status) {
            'closed' => 'CLOSED',
            'suspended' => 'SUSPENDED',
            default => $limit > 0 && $exposure >= $limit
                ? 'LIMIT_REACHED'
                : ($limit > 0 && $exposure >= ($limit * 0.8) ? 'WARNING_THRESHOLD' : 'ACTIVE'),
        };

        return [
            'uuid' => $point->uuid,
            'code' => $point->agency->code,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'department' => $point->agency->department,
            'country' => $point->agency->country,
            'status' => $status,
            'raw_status' => $point->status,
            'offer' => 'voyage',
            'type' => $point->organization_id !== null ? 'ORGANIZATION_ATTACHED' : 'AUTONOMOUS',
            'organization_name' => $point->organization?->name,
            'organization_uuid' => $point->organization?->uuid,
            'logo_url' => $point->getFirstMediaUrl('logo', 'thumb') ?: null,
            'organization_logo_url' => $point->organization?->getFirstMediaUrl('logo', 'thumb') ?: null,
            'manager_name' => $point->responsible_name,
            'responsible_name' => $point->responsible_name,
            'phone' => $point->phone,
            'address' => $point->address,
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'terms' => $terms,
            'outstanding_amount' => $exposure,
            'current_exposure' => $exposure,
            'credit_limit' => $limit,
            'usage_percentage' => $usage,
            'commission_rate_bps' => (int) ($terms['commission_basis_points'] ?? 0),
            'settlement_frequency_days' => $freqDays,
            'grace_period_days' => $this->terms->graceDays(),
            'daily_sales_amount' => (int) $point->getAttribute('gross_total'),
            'monthly_sales_amount' => (int) $point->getAttribute('gross_total'),
            'earned_commission_amount' => (int) $point->getAttribute('commission_total'),
            'gross_sales_amount' => (int) $point->getAttribute('gross_total'),
            'refunded_amount' => (int) $point->getAttribute('refunded_total'),
            'sales_count' => (int) $point->getAttribute('sales_count'),
            'overdue' => (bool) $point->getAttribute('overdue'),
            'created_at' => $point->created_at?->format('d M Y') ?? '',
        ];
    }

    public function allocatedCreditLimit(KoraPointOrganization $organization): int
    {
        return (int) $organization->points()
            ->withSum('entries as allocation_exposure', 'amount')
            ->get()
            ->sum(function (KoraPoint $point): int {
                $exposure = (int) ($point->getAttribute('allocation_exposure') ?? 0);

                if ($point->status === 'closed' && $exposure === 0) {
                    return 0;
                }

                return max(0, (int) ($point->ceiling_amount ?? 0));
            });
    }

    /** @return array<string, mixed> */
    /**
     * Maps a sale model to its public representation without internal identifiers.
     *
     * @return array<string, mixed>
     */
    public function sale(KoraPointSale $sale): array
    {
        return ['uuid' => $sale->uuid, 'point_uuid' => $sale->point->uuid, 'reservation_uuid' => $sale->reservation->uuid, 'status' => $sale->status, 'gross_amount' => $sale->gross_amount,
            'commission_amount' => $sale->commission_amount, 'commission_reversed' => $sale->commission_reversed,
            'net_amount' => $sale->net_amount, 'outstanding_amount' => $sale->outstanding_amount,
            'refunded_amount' => $sale->refunded_amount, 'terms' => $sale->terms_snapshot,
            'paid_at' => $sale->paid_at->toIso8601String(), 'due_at' => $sale->due_at->toIso8601String()];
    }

    /** @return Builder<KoraPartnerDocument> */
    /**
     * Returns the documents query visible for a point, including shared organization pieces.
     *
     * @return Builder<KoraPartnerDocument>
     */
    private function documents(User $actor, KoraPoint $point): Builder
    {
        $saleIds = $this->access->sales($actor)->select('id');

        return KoraPartnerDocument::query()->where(function ($query) use ($point): void {
            $query->where('point_id', $point->getKey());
            if ($point->organization_id !== null) {
                $query->orWhere(fn ($shared) => $shared->where('organization_id', $point->organization_id)->whereNull('point_id')->whereNull('remittance_id')->whereNull('refund_id'));
            }
        })->where(function ($query) use ($saleIds): void {
            $query->where(fn ($ordinary) => $ordinary->whereNull('refund_id')->whereNull('remittance_id'))
                ->orWhereHas('refund', fn ($refund) => $refund->whereIn('sale_id', $saleIds))
                ->orWhereHas('remittance.lines', fn ($line) => $line->whereIn('sale_id', $saleIds));
        });
    }

    /** @return array<string, mixed> */
    /**
     * Maps a partner document to its public representation with a download URL.
     *
     * @return array<string, mixed>
     */
    public function document(KoraPartnerDocument $document): array
    {
        return ['uuid' => $document->uuid, 'kind' => $document->kind, 'title' => $document->title,
            'reference' => $document->reference, 'signed_at' => $document->signed_at?->toDateString(),
            'expires_at' => $document->expires_at?->toDateString(), 'download_url' => route('kora-points.documents.download', $document->uuid)];
    }

    /** @return array<string, mixed> */
    public function visit(KoraPointVisit $visit, User $actor): array
    {
        $photoRoute = $actor->hasRole('kora_point_agent')
            ? 'kora-point.visits.photos.show'
            : ($this->access->isPartner($actor) ? 'kora-partner.visits.photos.show' : 'kora-points.visits.photos.show');

        return [
            'uuid' => $visit->uuid,
            'type' => $visit->visit_type,
            'score' => $visit->score,
            'report' => $visit->report,
            'status' => $visit->status,
            'visited_at' => $visit->created_at?->toIso8601String(),
            'visitor_name' => $visit->creator?->name,
            'actions' => $visit->actions->map(fn ($action): array => [
                'uuid' => $action->uuid,
                'label' => $action->label,
                'assignee_uuid' => $action->assignee?->uuid,
                'assignee_name' => $action->assignee?->name,
                'due_date' => $action->due_date->toDateString(),
                'status' => $action->status,
                'realization_note' => $action->realization_note,
                'review_reason' => $action->review_reason,
                'realized_at' => $action->realized_at?->toIso8601String(),
                'reviewed_at' => $action->reviewed_at?->toIso8601String(),
            ])->all(),
            'photos' => $visit->photos->map(fn ($photo): array => [
                'uuid' => $photo->uuid,
                'name' => $photo->original_name,
                'mime_type' => $photo->mime_type,
                'download_url' => route($photoRoute, ['visit' => $visit->uuid, 'photo' => $photo->uuid]),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function plvOrder(KoraPointPlvOrder $order): array
    {
        return [
            'uuid' => $order->uuid,
            'point_uuid' => $order->point?->uuid,
            'point_name' => $order->point?->agency?->name,
            'support' => $order->support,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'created_at' => $order->created_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'installed_at' => $order->installed_at?->toIso8601String(),
            'cancellation_reason' => $order->cancellation_reason,
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'creator_name' => $order->creator?->name,
        ];
    }
}
