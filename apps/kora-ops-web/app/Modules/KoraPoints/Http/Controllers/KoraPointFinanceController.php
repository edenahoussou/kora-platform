<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointFinanceController extends Controller
{
    public function __construct(private readonly KoraPointAccess $access) {}

    /**
     * Renders the finance cockpit (settlement queue, refund arbitration, KPIs).
     */
    public function index(Request $request): Response
    {
        $this->access->authorize($request->user(), 'kora-points.finance');

        $settlementsQuery = KoraPointRemittance::query()
            ->with(['lines.sale.point.agency', 'lines.sale.reservation.passengers'])
            ->latest('id');

        $refundsQuery = KoraPointRefund::query()
            ->with(['sale.point.agency', 'sale.reservation.passengers', 'sale.payment'])
            ->latest('id');

        $settlements = $settlementsQuery->paginate(30, ['*'], 'settlements_page')
            ->through(fn (KoraPointRemittance $remittance): array => $this->presentSettlement($remittance));

        $refunds = $refundsQuery->paginate(30, ['*'], 'refunds_page')
            ->through(fn (KoraPointRefund $refund): array => $this->presentRefund($refund));

        $groupSettlements = KoraPointRemittance::query()
            ->whereNotNull('organization_id')
            ->with(['lines.sale.point.agency'])
            ->latest('id')
            ->get()
            ->map(fn (KoraPointRemittance $remittance): array => $this->presentGroupSettlement($remittance))
            ->all();

        return Inertia::render('kora-points/finance/index', [
            'settlements' => $settlements->items(),
            'refunds' => $refunds->items(),
            'groupSettlements' => $groupSettlements,
            'stats' => $this->calculateStats(),
        ]);
    }

    /** @return array<string, mixed> */
    /**
     * Maps a remittance to the settlement shape consumed by the finance UI.
     *
     * @return array<string, mixed>
     */
    private function presentSettlement(KoraPointRemittance $remittance): array
    {
        $firstLine = $remittance->lines->first();
        $point = $firstLine?->sale?->point;
        $agency = $point?->agency;
        $declarer = User::query()->find($remittance->declared_by);
        $validator = $remittance->validated_by ? User::query()->find($remittance->validated_by) : null;

        $statusMap = [
            'draft' => 'DRAFT',
            'submitted' => 'DECLARED',
            'reconciliation' => 'RECONCILIATION',
            'validated' => 'VALIDATED',
            'rejected' => 'REJECTED',
        ];
        $status = $statusMap[$remittance->status] ?? strtoupper($remittance->status);

        $methodMap = [
            'bank_transfer' => 'BANK_TRANSFER',
            'bank_deposit' => 'BANK_DEPOSIT',
            'mobile_money' => 'MOBILE_MONEY',
        ];
        $method = $methodMap[$remittance->method ?? ''] ?? 'BANK_TRANSFER';

        $doc = KoraPartnerDocument::query()->where('remittance_id', $remittance->getKey())->first();

        $ticketsCovered = $remittance->lines->map(fn ($l) => $l->sale->uuid)->filter()->values()->all();

        $timeline = [
            [
                'step' => 'Création du bordereau',
                'date_label' => $remittance->created_at?->format('d M H:i') ?? '',
                'actor' => $declarer ? $declarer->name : 'Agent de point',
                'comment' => sprintf('Bordereau préparé pour %d vente(s).', $remittance->lines->count()),
            ],
        ];

        if ($remittance->submitted_at) {
            $timeline[] = [
                'step' => 'Preuve soumise et Déclaration',
                'date_label' => $remittance->submitted_at->format('d M H:i'),
                'actor' => $declarer ? $declarer->name : 'Agent de point',
                'comment' => $remittance->reference ? 'Réf '.$remittance->reference : 'Bordereau transmis',
            ];
        }

        if ($remittance->validated_at) {
            $timeline[] = [
                'step' => $status === 'VALIDATED' ? 'Fonds crédités et rapprochés' : 'Contrôle Finance KORA',
                'date_label' => $remittance->validated_at->format('d M H:i'),
                'actor' => $validator ? $validator->name : 'Finance KORA',
                'comment' => $remittance->note ?? 'Rapprochement effectué',
            ];
        }

        return [
            'uuid' => $remittance->uuid,
            'reference' => $remittance->reference ?: 'REV-'.$remittance->uuid,
            'point_uuid' => $point ? $point->uuid : '',
            'point_name' => $agency ? $agency->name : 'Point KORA',
            'created_by_name' => $declarer ? $declarer->name : 'Agent',
            'period_start' => $remittance->created_at?->format('Y-m-d') ?? '',
            'period_end' => $remittance->created_at?->format('Y-m-d') ?? '',
            'created_at' => $remittance->created_at?->format('Y-m-d H:i') ?? '',
            'declared_at' => $remittance->submitted_at?->format('Y-m-d H:i'),
            'validated_at' => $remittance->validated_at?->format('Y-m-d H:i'),
            'amount_expected' => $remittance->amount,
            'amount_declared' => $remittance->amount,
            'amount_validated' => $remittance->received_amount,
            'payment_method' => $method,
            'payment_reference' => $remittance->reference ?? 'REF-AUTO',
            'proof_file_name' => $doc ? $doc->title : 'bordereau_versement.pdf',
            'proof_file_size' => '320 Ko',
            'status' => $status,
            'sales_count' => $remittance->lines->count(),
            'tickets_covered' => $ticketsCovered,
            'notes' => $remittance->note,
            'rejection_reason' => $status === 'REJECTED' ? $remittance->note : null,
            'timeline' => $timeline,
        ];
    }

    /** @return array<string, mixed> */
    /**
     * Maps a refund to the arbitration shape consumed by the finance UI.
     *
     * @return array<string, mixed>
     */
    private function presentRefund(KoraPointRefund $refund): array
    {
        $sale = $refund->sale;
        $point = $sale?->point;
        $agency = $point?->agency;
        $requester = User::query()->find($refund->requested_by);
        $authorizer = $refund->authorized_by ? User::query()->find($refund->authorized_by) : null;
        $executor = $refund->executed_by ? User::query()->find($refund->executed_by) : null;

        $statusMap = [
            'requested' => 'REQUESTED',
            'authorized' => 'APPROVED_BY_FINANCE',
            'executed' => 'FUNDS_RESTITUTED',
            'confirmed' => 'FUNDS_RESTITUTED',
            'rejected' => 'REJECTED',
        ];
        $status = $statusMap[$refund->status] ?? strtoupper($refund->status);

        $passenger = $refund->passenger_id ? $sale?->reservation?->passengers()->whereKey($refund->passenger_id)->first() : $sale?->reservation?->passengers->first();
        $passengerName = $passenger ? ($passenger->first_name.' '.$passenger->last_name) : (($sale && $sale->payment && $sale->payment->payer_name) ? $sale->payment->payer_name : 'Passager');
        $passengerPhone = ($sale && $sale->payment && $sale->payment->payer_phone) ? $sale->payment->payer_phone : (($sale && $sale->reservation && $sale->reservation->payer) ? $sale->reservation->payer->phone : '+229 97 00 00 00');

        $doc = KoraPartnerDocument::query()->where('refund_id', $refund->getKey())->first();

        $timeline = [
            [
                'step' => 'Demande de remboursement',
                'date_label' => $refund->created_at?->format('d M H:i') ?? '',
                'actor' => $requester ? $requester->name : 'Agent de guichet',
                'detail' => $refund->reason ?? 'Demande initiée',
            ],
        ];

        if ($refund->authorized_by) {
            $timeline[] = [
                'step' => $refund->status === 'rejected' ? 'Demande refusée par Finance' : 'Autorisation Finance KORA',
                'date_label' => $refund->updated_at?->format('d M H:i') ?? '',
                'actor' => $authorizer ? $authorizer->name : 'Finance KORA',
                'detail' => $refund->status === 'rejected' ? 'Motif non recevable' : 'Remboursement autorisé',
            ];
        }

        if ($refund->executed_at) {
            $timeline[] = [
                'step' => 'Restitution des fonds',
                'date_label' => $refund->executed_at->format('d M H:i'),
                'actor' => $executor ? $executor->name : 'Agent',
                'detail' => $refund->reference ? 'Réf '.$refund->reference : 'Fonds restitués',
            ];
        }

        return [
            'uuid' => $refund->uuid,
            'reference' => 'REM-'.substr($refund->uuid, 0, 8),
            'ticket_number' => 'TK-'.substr($sale ? $sale->uuid : '0000', 0, 8),
            'sale_uuid' => $sale ? $sale->uuid : '',
            'passenger_name' => $passengerName,
            'passenger_phone' => $passengerPhone,
            'departure_ref' => 'Ligne Bus',
            'route_label' => $agency ? ($agency->city.' - Destination') : 'Trajet Régional',
            'original_amount' => $sale ? $sale->gross_amount : ($refund->amount ?? 0),
            'retained_fee' => 0,
            'refund_amount' => $refund->amount ?? ($sale ? $sale->gross_amount : 0),
            'commission_clawback' => $sale ? $sale->commission_amount : 0,
            'reason' => 'TRIP_CANCELLED',
            'reason_label' => $refund->reason ?: 'Annulation voyageur',
            'status' => $status,
            'requested_at' => $refund->created_at?->format('d M H:i') ?? '',
            'requested_by' => $requester ? $requester->name : 'Agent',
            'approved_at' => $refund->authorized_by ? $refund->updated_at?->format('d M H:i') : null,
            'approved_by' => $authorizer ? $authorizer->name : null,
            'restituted_at' => $refund->executed_at?->format('d M H:i'),
            'restituted_by' => $executor ? $executor->name : null,
            'receipt_signed' => true,
            'rejection_reason' => $status === 'REJECTED' ? ($refund->reason ?? 'Rejet Finance') : null,
            'proof_document' => $doc ? $doc->title : null,
            'timeline' => $timeline,
        ];
    }

    /** @return array<string, mixed> */
    /**
     * Maps an organization remittance with its per-sub-point breakdown.
     *
     * @return array<string, mixed>
     */
    private function presentGroupSettlement(KoraPointRemittance $remittance): array
    {
        $org = $remittance->organization_id ? KoraPointOrganization::query()->find($remittance->organization_id) : null;
        $declarer = User::query()->find($remittance->declared_by);

        $breakdown = $remittance->lines->groupBy('sale.point_id')->map(function ($lines) {
            $point = $lines->first()?->sale?->point;

            return [
                'subpoint_uuid' => $point ? $point->uuid : '',
                'subpoint_name' => $point ? $point->agency->name : 'Bureau',
                'current_exposure' => (int) $point?->entries()->sum('amount'),
                'amount_allocated' => (int) $lines->sum('amount'),
            ];
        })->values()->all();

        return [
            'uuid' => $remittance->uuid,
            'reference' => $remittance->reference ?: 'REV-ORG-'.substr($remittance->uuid, 0, 8),
            'organization_name' => $org ? $org->name : 'Organisation Partenaire',
            'created_by_name' => $declarer ? $declarer->name : 'Trésorier',
            'created_at' => $remittance->created_at?->format('d M Y à H:i') ?? '',
            'total_amount_expected' => $remittance->amount,
            'total_amount_declared' => $remittance->amount,
            'amount_validated' => $remittance->received_amount,
            'payment_method' => 'BANK_TRANSFER',
            'payment_reference' => $remittance->reference ?? 'VIR-REF',
            'proof_file_name' => 'ordre_virement.pdf',
            'status' => $remittance->status === 'validated' ? 'VALIDATED' : ($remittance->status === 'rejected' ? 'REJECTED' : 'DECLARED'),
            'breakdown' => $breakdown,
            'notes' => $remittance->note,
            'timeline' => [
                [
                    'step' => 'Déclaration transmise',
                    'date_label' => $remittance->created_at?->format('d M H:i') ?? '',
                    'actor' => $declarer ? $declarer->name : 'Trésorier',
                ],
            ],
        ];
    }

    /** @return array<string, int> */
    /**
     * Computes the consolidated finance KPIs (amounts to reconcile, pending refunds, monthly validated).
     *
     * @return array<string, int>
     */
    private function calculateStats(): array
    {
        return [
            'totalToReconcile' => (int) KoraPointRemittance::query()->whereIn('status', ['submitted', 'reconciliation'])->sum('amount'),
            'refundsInArbitration' => (int) KoraPointRefund::query()->where('status', 'requested')->count(),
            'monthlyValidatedRemittances' => (int) KoraPointRemittance::query()->where('status', 'validated')->whereMonth('validated_at', now()->month)->sum('received_amount'),
            'totalRemittancesCount' => (int) KoraPointRemittance::query()->count(),
            'totalRefundsCount' => (int) KoraPointRefund::query()->count(),
        ];
    }
}
