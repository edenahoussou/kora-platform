<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\KoraPoints\Actions\DeclareKoraPointRemittanceAction;
use App\Modules\KoraPoints\Http\Requests\CreateKoraPointRemittanceRequest;
use App\Modules\KoraPoints\Http\Requests\ReceiveKoraPointRemittanceRequest;
use App\Modules\KoraPoints\Http\Requests\StoreKoraPartnerRemittanceRequest;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointPartnerReadService;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointRemittanceController extends Controller
{
    public function __construct(
        private readonly KoraPointRemittanceService $service,
        private readonly KoraPointAccess $access,
        private readonly KoraPointPartnerReadService $partnerRead,
        private readonly DeclareKoraPointRemittanceAction $declareRemittance,
    ) {}

    /**
     * Renders the remittance slips visible to the actor.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $this->access->authorize($actor, 'kora-points.view');

        if ($this->access->isOrganizationPartner($actor)) {
            return $this->partnerIndex($actor);
        }

        $saleIds = $this->access->sales($actor)->select('id');
        $query = KoraPointRemittance::query()->whereHas('lines', fn ($query) => $query->whereIn('sale_id', $saleIds));
        $eligibleSales = [];
        $summary = [
            'outstanding_amount' => 0,
            'eligible_count' => 0,
            'eligible_amount' => 0,
            'pending_amount' => 0,
            'validated_amount' => 0,
        ];

        if ($request->user()->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])) {
            $claimedSaleIds = KoraPointRemittanceLine::query()->whereNotNull('claimed_sale_id')->select('claimed_sale_id');
            $organizationSales = $this->access->sales($request->user())
                ->where('organization_id', $request->user()->kora_point_organization_id);
            $eligibleQuery = (clone $organizationSales)
                ->where('outstanding_amount', '>', 0)
                ->whereDoesntHave('refunds', fn ($refunds) => $refunds->whereNotIn('status', ['confirmed', 'rejected']))
                ->whereNotIn('id', $claimedSaleIds);
            $organizationRemittances = KoraPointRemittance::query()
                ->where('organization_id', $request->user()->kora_point_organization_id);
            $summary = [
                'outstanding_amount' => (int) (clone $organizationSales)->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
                'eligible_count' => (clone $eligibleQuery)->count(),
                'eligible_amount' => (int) (clone $eligibleQuery)->sum('outstanding_amount'),
                'pending_amount' => (int) (clone $organizationRemittances)->whereIn('status', ['submitted', 'reconciliation'])->sum('amount'),
                'validated_amount' => (int) (clone $organizationRemittances)->where('status', 'validated')->sum('received_amount'),
            ];
            $eligibleSales = $eligibleQuery
                ->with('point.agency')
                ->latest('id')
                ->limit(500)
                ->get()
                ->map(fn (KoraPointSale $sale): array => [
                    'uuid' => $sale->uuid,
                    'point_name' => $sale->point->agency->name,
                    'point_code' => $sale->point->agency->code,
                    'amount' => $sale->outstanding_amount,
                    'paid_at' => $sale->paid_at->toIso8601String(),
                ])
                ->all();
        }

        return Inertia::render('kora-points/remittances/index', [
            'organization_name' => $request->user()->koraPointOrganization()->value('name') ?? 'Réseau KORA POINT',
            'remittances' => $query->latest('id')->paginate(30)->through(fn (KoraPointRemittance $remittance): array => $this->present($remittance)),
            'eligible_sales' => $eligibleSales,
            'remittance_summary' => $summary,
        ]);
    }

    /**
     * Calculates the same period preview as the autonomous Point flow across an organization.
     */
    public function preview(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->access->authorize($actor, 'kora-points.remit');
        abort_unless($this->access->isOrganizationPartner($actor), 403);
        $organization = $actor->koraPointOrganization()->firstOrFail();
        $this->access->requireOrganization($actor, $organization);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $salesQuery = $this->eligibleSales($actor, $organization);

        if (! empty($validated['from'])) {
            $salesQuery->whereDate('paid_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $salesQuery->whereDate('paid_at', '<=', $validated['to']);
        }

        $ticketsCount = (clone $salesQuery)->count();
        $saleUuids = (clone $salesQuery)->orderBy('paid_at')->limit(500)->pluck('uuid')->all();

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $validated['from'] ?? now()->toDateString(),
                'to' => $validated['to'] ?? now()->toDateString(),
                'tickets_count' => $ticketsCount,
                'gross_amount' => (int) (clone $salesQuery)->sum('gross_amount'),
                'commission_amount' => (int) (clone $salesQuery)->sum('commission_amount'),
                'net_amount' => (int) (clone $salesQuery)->sum('outstanding_amount'),
                'sale_uuids' => $saleUuids,
            ],
        ]);
    }

    /**
     * Declares one versement using the autonomous Point form and the selected organization sales.
     */
    public function declare(StoreKoraPartnerRemittanceRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $this->access->authorize($actor, 'kora-points.remit');
        $organization = $actor->koraPointOrganization()->firstOrFail();
        $this->access->requireOrganization($actor, $organization);
        $data = $request->validated();
        $proof = $request->file('proof');
        if (! $proof instanceof UploadedFile) {
            throw ValidationException::withMessages(['proof' => 'La preuve de versement est obligatoire.']);
        }

        $this->declareRemittance->execute(
            $actor,
            array_values(array_map('strval', $data['sale_uuids'])),
            (string) $data['method'],
            (string) $data['reference'],
            $proof,
            $data['bank_name'] ?? null,
            $data['notes'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Versement déclaré pour le réseau. Les fonds sont en attente de vérification par la Trésorerie KORA.',
        ]);

        return to_route('kora-points.remittances.index');
    }

    /**
     * Renders a single remittance detail with its covered sales.
     */
    public function show(Request $request, KoraPointRemittance $remittance): Response
    {
        $this->service->requireView($request->user(), $remittance);

        $hasProof = KoraPartnerDocument::query()
            ->where('remittance_id', $remittance->getKey())
            ->where('kind', 'remittance_proof')
            ->whereHas('media')
            ->exists();

        return Inertia::render('kora-points/remittances/show', [
            'organization_name' => $request->user()->koraPointOrganization()->value('name') ?? 'Réseau KORA POINT',
            'remittance' => $this->present($remittance),
            'lines' => $remittance->lines()->with('sale.point.agency')->get()->map(fn (KoraPointRemittanceLine $line): array => [
                'sale_uuid' => $line->sale->uuid,
                'point_uuid' => $line->sale->point->uuid,
                'point_name' => $line->sale->point->agency->name,
                'point_code' => $line->sale->point->agency->code,
                'amount' => $line->amount,
            ])->all(),
            'has_proof' => $hasProof,
            'can_submit' => $request->user()->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])
                && $remittance->declared_by === $request->user()->getKey()
                && $remittance->status === 'draft',
        ]);
    }

    /**
     * Renders the existing Point settlement screen with organization-wide records and totals.
     */
    private function partnerIndex(User $actor): Response
    {
        $organization = $actor->koraPointOrganization()->firstOrFail();
        $this->access->requireOrganization($actor, $organization);
        $workspace = $this->partnerRead->dashboard($actor, $organization);
        $saleIds = $this->access->sales($actor)
            ->where('organization_id', $organization->getKey())
            ->select('id');

        $remittances = KoraPointRemittance::query()
            ->where('organization_id', $organization->getKey())
            ->whereHas('lines', fn ($lines) => $lines->whereIn('sale_id', $saleIds))
            ->with(['lines.sale.point.agency', 'declarant'])
            ->latest('id')
            ->paginate(30);

        $settlements = $remittances->getCollection()
            ->map(fn (KoraPointRemittance $remittance): array => $this->presentSettlementRecord($remittance, $organization))
            ->all();

        return Inertia::render('kora-points/partner/remittances', [
            'organization_name' => $organization->name,
            'point' => $workspace['profile'],
            'health' => $workspace['health'],
            'settlements' => $settlements,
            'koraPaymentDetails' => $this->paymentDetails(),
        ]);
    }

    /**
     * Returns eligible outstanding sales limited to this actor's active organization.
     *
     * @return Builder<KoraPointSale>
     */
    private function eligibleSales(User $actor, KoraPointOrganization $organization): Builder
    {
        $claimedSaleIds = KoraPointRemittanceLine::query()
            ->whereNotNull('claimed_sale_id')
            ->select('claimed_sale_id');

        return $this->access->sales($actor)
            ->where('organization_id', $organization->getKey())
            ->where('outstanding_amount', '>', 0)
            ->whereDoesntHave('refunds', fn ($refunds) => $refunds->whereNotIn('status', ['confirmed', 'rejected']))
            ->whereNotIn('id', $claimedSaleIds);
    }

    /** @return array<string, mixed> */
    private function presentSettlementRecord(KoraPointRemittance $remittance, KoraPointOrganization $organization): array
    {
        $sales = $remittance->lines
            ->map(fn (KoraPointRemittanceLine $line): ?KoraPointSale => $line->sale)
            ->filter();
        $pointNames = $sales
            ->map(fn (KoraPointSale $sale): ?string => $sale->point?->agency?->name)
            ->filter()
            ->unique()
            ->values();
        $paidAt = $sales->pluck('paid_at')->filter();
        $status = match ($remittance->status) {
            'submitted' => 'DECLARED',
            'validated' => 'VALIDATED',
            'reconciliation' => 'PARTIALLY_VALIDATED',
            'rejected' => 'REJECTED',
            default => 'DRAFT',
        };
        $paymentMethod = match ($remittance->method) {
            'bank_deposit' => 'BANK_DEPOSIT',
            'check' => 'CHECK',
            'mobile_money' => 'MOBILE_MONEY',
            default => 'BANK_TRANSFER',
        };
        $declarantName = $remittance->declarant->name;
        $createdAt = $remittance->created_at;
        $periodStart = $paidAt->min();
        $periodEnd = $paidAt->max();
        $timeline = [[
            'step' => 'Déclaration créée',
            'date_label' => $createdAt?->format('d/m/Y H:i') ?? '—',
            'actor' => $declarantName,
        ]];

        if ($remittance->submitted_at !== null) {
            $timeline[] = [
                'step' => 'Transmise à Finance KORA',
                'date_label' => $remittance->submitted_at->format('d/m/Y H:i'),
                'actor' => $declarantName,
            ];
        }
        if ($remittance->validated_at !== null) {
            $timeline[] = [
                'step' => 'Rapprochée et validée',
                'date_label' => $remittance->validated_at->format('d/m/Y H:i'),
                'actor' => 'Finance KORA',
            ];
        }

        return [
            'uuid' => $remittance->uuid,
            'reference' => $remittance->reference ?: 'REV-'.strtoupper(substr($remittance->uuid, 0, 8)),
            'point_uuid' => $organization->uuid,
            'point_name' => $pointNames->isNotEmpty() ? $pointNames->join(', ') : $organization->name,
            'created_by_name' => $declarantName,
            'period_start' => $periodStart?->toDateString() ?? $createdAt?->toDateString() ?? '',
            'period_end' => $periodEnd?->toDateString() ?? $remittance->submitted_at?->toDateString() ?? $createdAt?->toDateString() ?? '',
            'created_at' => $createdAt?->format('d/m/Y H:i') ?? '',
            'declared_at' => $remittance->submitted_at?->toIso8601String(),
            'validated_at' => $remittance->validated_at?->toIso8601String(),
            'amount_expected' => $remittance->amount,
            'amount_declared' => $remittance->amount,
            'amount_validated' => $remittance->received_amount,
            'payment_method' => $paymentMethod,
            'payment_reference' => $remittance->reference,
            'status' => $status,
            'sales_count' => $remittance->lines->count(),
            'tickets_covered' => $sales->map(fn (KoraPointSale $sale): string => 'TK-'.strtoupper(substr($sale->uuid, 0, 8)))->all(),
            'notes' => $remittance->note,
            'rejection_reason' => $remittance->status === 'rejected' ? $remittance->note : null,
            'timeline' => $timeline,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentDetails(): array
    {
        return [
            'bank_accounts' => [
                ['bank' => 'Bank of Africa (BOA Bénin)', 'country' => 'Bénin', 'flag' => '🇧🇯', 'rib' => 'BJ061 01001 001234567890 12', 'account_number' => '001234567890', 'account_name' => 'KORA TRANSPORT & MOBILITY SA', 'swift' => 'AFRIBJBJ'],
                ['bank' => 'Ecobank Bénin', 'country' => 'Bénin', 'flag' => '🇧🇯', 'rib' => 'BJ062 01002 009876543210 34', 'account_number' => '009876543210', 'account_name' => 'KORA TRANSPORT & MOBILITY SA', 'swift' => 'ECOCBJBJ'],
                ['bank' => 'BGFIBank Côte d’Ivoire', 'country' => 'Côte d’Ivoire', 'flag' => '🇨🇮', 'rib' => 'CI078 01005 005544332211 56', 'account_number' => '005544332211', 'account_name' => 'KORA TRANSPORT & MOBILITY SA', 'swift' => 'BGFICIAB'],
            ],
            'check_order' => 'KORA TRANSPORT & MOBILITY SA',
            'mobile_money_merchants' => [
                ['network' => 'mtn_momo', 'label' => 'MTN MoMo Marchand', 'merchant_code' => 'KORA-MOMO-01', 'phone' => '+229 97 00 00 00', 'country' => 'Bénin', 'flag' => '🇧🇯'],
                ['network' => 'moov_flooz', 'label' => 'Moov Flooz Marchand', 'merchant_code' => 'FLOOZ-KORA-01', 'phone' => '+229 95 00 00 00', 'country' => 'Bénin', 'flag' => '🇧🇯'],
                ['network' => 'wave_ci', 'label' => 'Wave CI Marchand', 'merchant_code' => 'WAVE-KORA-CI', 'phone' => '+225 07 00 00 00', 'country' => 'Côte d’Ivoire', 'flag' => '🇨🇮'],
                ['network' => 'tmoney_tg', 'label' => 'T-Money Marchand', 'merchant_code' => 'TMONEY-KORA', 'phone' => '+228 90 00 00 00', 'country' => 'Togo', 'flag' => '🇹🇬'],
            ],
        ];
    }

    /**
     * Creates a draft remittance from the selected sales.
     */
    public function store(CreateKoraPointRemittanceRequest $request): RedirectResponse
    {
        $remittance = $this->service->create($request->user(), $request->validated('sale_uuids'), $request->validated('method'), $request->validated('reference'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bordereau préparé.']);

        return to_route('kora-points.remittances.show', $remittance->uuid);
    }

    /**
     * Declares the remittance to KORA finance for control.
     */
    public function submit(Request $request, KoraPointRemittance $remittance): RedirectResponse
    {
        $this->service->submit($request->user(), $remittance);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reversement déclaré ; réception KORA en attente.']);

        return back();
    }

    /**
     * Records the finance reception decision (validate, reconcile or reject).
     */
    public function receive(ReceiveKoraPointRemittanceRequest $request, KoraPointRemittance $remittance): RedirectResponse
    {
        $this->service->receive($request->user(), $remittance, (int) $request->validated('received_amount'), $request->boolean('reject'), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contrôle du reversement enregistré.']);

        return back();
    }

    /** @return array<string, mixed> */
    /**
     * Maps a remittance to its public representation without internal identifiers.
     *
     * @return array<string, mixed>
     */
    private function present(KoraPointRemittance $remittance): array
    {
        return ['uuid' => $remittance->uuid, 'status' => $remittance->status, 'amount' => $remittance->amount,
            'received_amount' => $remittance->received_amount, 'method' => $remittance->method, 'reference' => $remittance->reference,
            'note' => $remittance->note, 'submitted_at' => $remittance->submitted_at?->toIso8601String(), 'validated_at' => $remittance->validated_at?->toIso8601String()];
    }
}
