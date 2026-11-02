<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
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

        $organizations = KoraPointOrganization::query()
            ->with('media')
            ->latest('id')
            ->get()
            ->map(fn (KoraPointOrganization $org): array => [
                'uuid' => $org->uuid,
                'code' => $org->code,
                'name' => $org->name,
                'logo_url' => $org->getFirstMediaUrl('logo', 'thumb') ?: null,
                'legal_name' => $org->name,
                'headquarters_city' => $org->address ?? 'Cotonou',
                'rccm' => 'RB/COT/14 B 11842',
                'ifu' => '3201400293841',
                'points_count' => $org->points()->count(),
                'active_points_count' => $org->points()->where('status', 'active')->count(),
                'total_monthly_sales' => (int) $org->sales()->sum('gross_amount'),
                'total_exposure' => (int) $org->points()->withSum('entries as debt_total', 'amount')->get()->sum('debt_total'),
                'credit_limit' => (int) ($org->ceiling_amount ?? $org->points()->sum('ceiling_amount')),
                'default_terms' => [
                    'commission_rate_bps' => (int) ($org->commission_basis_points ?? 300),
                    'credit_limit' => (int) ($org->ceiling_amount ?? 1500000),
                    'settlement_frequency_days' => ($org->settlement_calendar['frequency'] ?? 'weekly') === 'monthly' ? 30 : 7,
                    'grace_period_days' => 7,
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

        if (in_array($filters['state'] ?? '', ['active', 'suspended'], true)) {
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

        return [
            'point' => $this->point($point, $actor),
            'sales' => $this->access->sales($actor)->where('point_id', $point->getKey())->with(['reservation', 'point'])->latest('id')->paginate(30)->through(fn (KoraPointSale $sale): array => $this->sale($sale)),
            'entries' => KoraPointEntry::query()->whereIn('sale_id', $this->access->sales($actor)->where('point_id', $point->getKey())->select('id'))->latest('id')->paginate(30, ['*'], 'entries_page')->through(fn (KoraPointEntry $entry): array => ['uuid' => $entry->uuid, 'type' => $entry->type, 'amount' => $entry->amount, 'commission_delta' => $entry->commission_delta, 'recorded_at' => $entry->created_at?->toIso8601String()]),
            'refunds' => KoraPointRefund::query()->whereIn('sale_id', $this->access->sales($actor)->where('point_id', $point->getKey())->select('id'))->latest('id')->paginate(30, ['*'], 'refunds_page')->through(fn ($refund): array => ['uuid' => $refund->uuid, 'status' => $refund->status, 'amount' => $refund->amount, 'executor' => $refund->executor]),
            'documents' => $this->documents($actor, $point)->latest('id')->get()->map(fn (KoraPartnerDocument $document): array => $this->document($document))->all(),
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

        $status = $point->status === 'suspended' ? 'SUSPENDED' : ($limit > 0 && $exposure >= $limit ? 'LIMIT_REACHED' : ($limit > 0 && $exposure >= ($limit * 0.8) ? 'WARNING_THRESHOLD' : 'ACTIVE'));

        return [
            'uuid' => $point->uuid,
            'code' => $point->agency->code,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'department' => $point->agency->city,
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
            'commission_rate_bps' => (int) ($terms['commission_basis_points'] ?? 300),
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
}
