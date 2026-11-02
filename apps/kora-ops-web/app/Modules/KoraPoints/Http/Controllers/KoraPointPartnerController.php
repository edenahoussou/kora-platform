<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointPartnerReadService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class KoraPointPartnerController extends Controller
{
    public function __construct(
        private readonly KoraPointAccess $access,
        private readonly KoraPointReadService $read,
        private readonly KoraPointPartnerReadService $partnerRead,
    ) {}

    public function dashboard(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $pointUuid = $this->pointFilter($actor, $organization, $request->query('point_uuid'));
        $dashboard = $this->partnerRead->dashboard($actor, $organization, $pointUuid);
        $recentSales = $this->partnerRead->sales($actor, $organization, $pointUuid, 8);

        return Inertia::render('kora-points/partner/dashboard', [
            'organization' => $this->organizationSummary($organization),
            ...$dashboard,
            'recent_sales' => $recentSales->items(),
            'recent_sales_total' => $recentSales->total(),
            'is_treasurer' => $actor->hasRole('kora_point_treasurer'),
        ]);
    }

    public function points(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $props = $this->read->index($actor, $request->only('q', 'state'));

        return Inertia::render('kora-points/index', $props + [
            'partnerWorkspace' => [
                'base_path' => '/kora-partner/points',
                'organization_uuid' => $organization->uuid,
                'organization_name' => $organization->name,
            ],
        ]);
    }

    public function point(Request $request, KoraPoint $point): Response
    {
        $actor = $request->user();
        $this->access->requirePoint($actor, $point);
        $organization = $this->organizationFor($actor);

        return Inertia::render('kora-points/partner/point', [
            ...$this->read->show($actor, $point),
            'organization' => $this->organizationSummary($organization),
            'can_manage_points' => $this->access->isOrganizationSupervisor($actor),
        ]);
    }

    public function sales(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $pointUuid = $this->pointFilter($actor, $organization, $request->query('point_uuid'));
        $sales = $this->partnerRead->sales($actor, $organization, $pointUuid);

        return Inertia::render('kora-points/partner/sales', [
            'organization' => $this->organizationSummary($organization),
            'sales' => $sales,
            'point_filter_options' => $this->pointFilterOptions($actor, $organization),
            'selected_point_uuid' => $pointUuid,
        ]);
    }

    public function commissions(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $period = in_array($request->query('period'), ['current_month', 'last_month', 'all'], true)
            ? (string) $request->query('period')
            : 'current_month';
        $pointUuid = $this->pointFilter($actor, $organization, $request->query('point_uuid'));
        $commissions = $this->partnerRead->commissions($actor, $organization, $period, $pointUuid);

        return Inertia::render('kora-points/partner/commissions', [
            'organization' => $this->organizationSummary($organization),
            ...$commissions,
            'point_filter_options' => $this->pointFilterOptions($actor, $organization),
            'selected_point_uuid' => $pointUuid,
        ]);
    }

    public function refunds(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $pointUuid = $this->pointFilter($actor, $organization, $request->query('point_uuid'));

        return Inertia::render('kora-points/partner/refunds', [
            'organization' => $this->organizationSummary($organization),
            'refunds' => $this->partnerRead->refunds($actor, $organization, $pointUuid),
            'refund_summary' => $this->partnerRead->refundSummary($actor, $organization, $pointUuid),
            'point_filter_options' => $this->pointFilterOptions($actor, $organization),
            'selected_point_uuid' => $pointUuid,
        ]);
    }

    public function exportCommissions(Request $request, FinanceExportService $exporter): SymfonyResponse
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $period = in_array($request->query('period'), ['current_month', 'last_month', 'all'], true)
            ? (string) $request->query('period')
            : 'current_month';
        $pointUuid = $this->pointFilter($actor, $organization, $request->query('point_uuid'));
        $rows = $this->partnerRead->commissionExportRows($actor, $organization, $period, $pointUuid);
        $filename = 'commissions-'.$organization->code.'-'.$period;
        $headers = ['Date', 'Point', 'Code Point', 'Billet', 'Montant brut (FCFA)', 'Commission initiale (FCFA)', 'Reprise (FCFA)', 'Commission nette (FCFA)', 'État'];

        if ($request->query('format', 'xlsx') === 'xlsx') {
            return $exporter->xlsx($filename.'.xlsx', $headers, $rows);
        }

        abort_unless($request->query('format') === 'pdf', 422);
        $escapedOrganization = e($organization->name);
        $escapedPeriod = e($this->partnerRead->periodLabel($period));
        $bodyRows = collect($rows)->map(fn (array $row): string => '<tr>'.collect($row)->map(fn (string|int|float|null $cell): string => '<td>'.e((string) ($cell ?? '—')).'</td>')->implode('').'</tr>')->implode('');
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>body{font:12px Arial,sans-serif;color:#13233a;padding:24px}h1{font-size:20px;margin:0 0 6px}p{color:#56657a;margin:0 0 20px}table{width:100%;border-collapse:collapse}th{background:#001d4e;color:#fff;text-align:left}td,th{border:1px solid #d9e0e8;padding:7px;font-size:10px}</style></head><body><h1>Relevé des commissions · '.$escapedOrganization.'</h1><p>Période : '.$escapedPeriod.'</p><table><thead><tr>'.collect($headers)->map(fn (string $header): string => '<th>'.e($header).'</th>')->implode('').'</tr></thead><tbody>'.$bodyRows.'</tbody></table></body></html>';
        $pdf = Browsershot::html($html)->noSandbox()->showBackground()->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    public function documents(Request $request): Response
    {
        $actor = $request->user();
        $organization = $this->organizationFor($actor);
        $documents = KoraPartnerDocument::query()
            ->where('organization_id', $organization->getKey())
            ->whereNull('point_id')
            ->whereNull('remittance_id')
            ->whereNull('refund_id')
            ->latest('id')
            ->get();

        return Inertia::render('kora-points/partner/documents', [
            'organization' => $this->organizationSummary($organization),
            'documents' => $documents->map(fn (KoraPartnerDocument $document): array => $this->read->document($document))->all(),
            'upload_url' => route('kora-points.organizations.documents', $organization->uuid),
        ]);
    }

    public function organization(Request $request): Response
    {
        $organization = $this->organizationFor($request->user());

        return Inertia::render('kora-points/partner/organization', [
            'organization' => $this->organizationSummary($organization),
        ]);
    }

    private function organizationFor(User $actor): KoraPointOrganization
    {
        $organization = $actor->koraPointOrganization()->firstOrFail();
        $this->access->requireOrganization($actor, $organization);

        return $organization->loadMissing('media');
    }

    private function pointFilter(User $actor, KoraPointOrganization $organization, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->access->points($actor)
            ->where('organization_id', $organization->getKey())
            ->where('uuid', $value)
            ->value('uuid') ?? abort(404);
    }

    /** @return array<int, array{uuid: string, name: string, code: string}> */
    private function pointFilterOptions(User $actor, KoraPointOrganization $organization): array
    {
        return $this->read->query($actor)
            ->where('organization_id', $organization->getKey())
            ->get()
            ->map(fn (KoraPoint $point): array => [
                'uuid' => $point->uuid,
                'name' => $point->agency->name,
                'code' => $point->agency->code,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function organizationSummary(KoraPointOrganization $organization): array
    {
        $allocated = $this->read->allocatedCreditLimit($organization);

        return [
            'uuid' => $organization->uuid,
            'code' => $organization->code,
            'name' => $organization->name,
            'logo_url' => $organization->getFirstMediaUrl('logo', 'thumb') ?: null,
            'country' => $organization->country,
            'registration_identifier' => $organization->registration_identifier,
            'tax_identifier' => $organization->tax_identifier,
            'status' => $organization->status,
            'phone' => $organization->phone,
            'address' => $organization->address,
            'commission_basis_points' => (int) $organization->commission_basis_points,
            'ceiling_amount' => (int) $organization->ceiling_amount,
            'allocated_amount' => $allocated,
            'available_allocation' => max(0, (int) $organization->ceiling_amount - $allocated),
            'settlement_calendar' => $organization->settlement_calendar,
        ];
    }
}
