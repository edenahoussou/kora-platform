<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashSession;
use App\Modules\KoraPoints\Actions\DeclareKoraPointRemittanceAction;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Models\KoraPointVisitAction;
use App\Modules\KoraPoints\Services\KoraPointAgentReadService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointAgentController extends Controller
{
    public function __construct(
        private readonly KoraPointAgentReadService $agentRead,
        private readonly DeclareKoraPointRemittanceAction $declareRemittance,
        private readonly KoraPointReadService $pointRead,
    ) {}

    /**
     * Display the main agent dashboard with operational KPIs and deferred activity metrics.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        return Inertia::render('kora-points/agent/dashboard', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'metrics' => Inertia::defer(fn (): array => $this->agentRead->metrics($point)),
            'priorities' => Inertia::defer(fn (): array => $this->agentRead->priorities($point)),
        ]);
    }

    /**
     * Display the point sale interface or redirect to the counter-sale workflow.
     */
    public function sale(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        return Inertia::render('kora-points/agent/sale', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
        ]);
    }

    /**
     * Display the agent physical cash register session management view.
     */
    public function cash(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        $today = now()->toDateString();
        $filters = [
            'from' => (string) $request->query('from', $today),
            'to' => (string) $request->query('to', $today),
            'channel' => (string) $request->query('channel', 'all'),
            'q' => (string) $request->query('q', ''),
        ];

        return Inertia::render('kora-points/agent/cash', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'brouillard' => $this->agentRead->dailyCashBrouillard($point, $user, $filters),
            'filters' => $filters,
            'cashSession' => $this->agentRead->cashSession($user),
        ]);
    }

    /**
     * Open a daily cash session for the authenticated agent.
     */
    public function openCash(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->agentRead->resolvePoint($user);

        $validated = $request->validate([
            'opening_amount' => ['required', 'integer', 'min:0'],
        ]);

        $session = CashSession::query()
            ->where('agent_id', $user->getKey())
            ->where('agency_id', $user->agency_id)
            ->where('status', 'open')
            ->first();

        if ($session === null) {
            CashSession::query()->create([
                'agent_id' => $user->getKey(),
                'agency_id' => $user->agency_id,
                'business_date' => now()->toDateString(),
                'opening_float' => $validated['opening_amount'],
                'status' => 'open',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Session de caisse ouverte avec succès.',
        ]);

        return back();
    }

    /**
     * Close the current open cash session for the authenticated agent.
     */
    public function closeCash(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->agentRead->resolvePoint($user);

        $validated = $request->validate([
            'actual_cash' => ['required', 'integer', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var CashSession|null $session */
        $session = CashSession::query()
            ->where('agent_id', $user->getKey())
            ->where('agency_id', $user->agency_id)
            ->where('status', 'open')
            ->first();

        if ($session !== null) {
            $expected = (int) ($session->opening_float + $session->total_cash);
            $actual = (int) $validated['actual_cash'];

            $session->update([
                'actual_balance' => $actual,
                'variance' => $actual - $expected,
                'closing_comment' => $validated['closing_note'] ?? null,
                'status' => 'closed',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Session de caisse clôturée avec succès.',
        ]);

        return back();
    }

    /**
     * Display the tickets sales history with deferred sales records.
     */
    public function tickets(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        return Inertia::render('kora-points/agent/tickets', [
            'point' => $this->agentRead->profile($point, $user),
            'sales' => Inertia::defer(fn (): array => $this->agentRead->sales($point, $user)),
        ]);
    }

    /**
     * Display the partner point profile, commercial terms, convention, and team members.
     */
    public function profile(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $details = $this->agentRead->profileDetails($point, $user);

        return Inertia::render('kora-points/agent/profile', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'convention' => Inertia::defer(fn (): array => $details['convention']),
            'documents' => Inertia::defer(fn (): array => $details['documents']),
            'assignedUsers' => Inertia::defer(fn (): array => $details['assignedUsers']),
        ]);
    }

    public function visitActions(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        abort_unless($user->can('kora-points.visits.actions.update'), 403);
        $actions = KoraPointVisitAction::query()
            ->where('assigned_user_id', $user->getKey())
            ->whereHas('visit', fn ($visits) => $visits->where('point_id', $point->getKey()))
            ->with(['visit.point.agency', 'visit.creator', 'visit.actions.assignee', 'visit.actions.reviewer', 'visit.photos'])
            ->latest('id')
            ->paginate(30, ['*'], 'actions_page')
            ->withQueryString()
            ->through(fn (KoraPointVisitAction $action): array => [
                'uuid' => $action->uuid,
                'visit_uuid' => $action->visit->uuid,
                'visit_type' => $action->visit->visit_type,
                'visit_status' => $action->visit->status,
                'point_name' => $action->visit->point->agency->name,
                'label' => $action->label,
                'due_date' => $action->due_date->toDateString(),
                'status' => $action->status,
                'realization_note' => $action->realization_note,
                'review_reason' => $action->review_reason,
                'photos' => $this->pointRead->visit($action->visit, $user)['photos'],
            ]);

        return Inertia::render('kora-points/agent/visit-actions', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'actions' => $actions,
        ]);
    }

    /**
     * Display earned commissions history and monthly exports.
     */
    public function commissions(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $period = in_array($request->query('period'), ['current_month', 'last_month', 'all'], true)
            ? (string) $request->query('period')
            : 'current_month';

        return Inertia::render('kora-points/agent/commissions', [
            'point' => $this->agentRead->profile($point, $user),
            'commissions' => Inertia::defer(fn (): array => $this->agentRead->commissions($point, $user, $period)),
        ]);
    }

    /**
     * Display settlements list and allow remittance declaration.
     */
    public function settlements(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        return Inertia::render('kora-points/agent/settlements', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'settlements' => Inertia::defer(fn (): array => $this->agentRead->settlements($point, $user)),
            'koraPaymentDetails' => [
                'bank_accounts' => [
                    [
                        'bank' => 'Bank of Africa (BOA Bénin)',
                        'country' => 'Bénin',
                        'flag' => '🇧🇯',
                        'rib' => 'BJ061 01001 001234567890 12',
                        'account_number' => '001234567890',
                        'account_name' => 'KORA TRANSPORT & MOBILITY SA',
                        'swift' => 'AFRIBJBJ',
                    ],
                    [
                        'bank' => 'Ecobank Bénin',
                        'country' => 'Bénin',
                        'flag' => '🇧🇯',
                        'rib' => 'BJ062 01002 009876543210 34',
                        'account_number' => '009876543210',
                        'account_name' => 'KORA TRANSPORT & MOBILITY SA',
                        'swift' => 'ECOCBJBJ',
                    ],
                    [
                        'bank' => 'BGFIBank Côte d’Ivoire',
                        'country' => 'Côte d’Ivoire',
                        'flag' => '🇨🇮',
                        'rib' => 'CI078 01005 005544332211 56',
                        'account_number' => '005544332211',
                        'account_name' => 'KORA TRANSPORT & MOBILITY SA',
                        'swift' => 'BGFICIAB',
                    ],
                ],
                'check_order' => 'KORA TRANSPORT & MOBILITY SA',
                'mobile_money_merchants' => [
                    [
                        'network' => 'mtn_momo',
                        'label' => 'MTN MoMo Marchand',
                        'merchant_code' => 'KORA-MOMO-01',
                        'phone' => '+229 97 00 00 00',
                        'country' => 'Bénin',
                        'flag' => '🇧🇯',
                    ],
                    [
                        'network' => 'moov_flooz',
                        'label' => 'Moov Flooz Marchand',
                        'merchant_code' => 'FLOOZ-KORA-01',
                        'phone' => '+229 95 00 00 00',
                        'country' => 'Bénin',
                        'flag' => '🇧🇯',
                    ],
                    [
                        'network' => 'wave_ci',
                        'label' => 'Wave CI Marchand',
                        'merchant_code' => 'WAVE-KORA-CI',
                        'phone' => '+225 07 00 00 00',
                        'country' => 'Côte d’Ivoire',
                        'flag' => '🇨🇮',
                    ],
                    [
                        'network' => 'tmoney_tg',
                        'label' => 'T-Money Marchand',
                        'merchant_code' => 'TMONEY-KORA',
                        'phone' => '+228 90 00 00 00',
                        'country' => 'Togo',
                        'flag' => '🇹🇬',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Preview unsettled sales and net amount due for a selected date range.
     */
    public function settlementPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = KoraPointSale::query()
            ->where('point_id', $point->getKey())
            ->where('outstanding_amount', '>', 0)
            ->whereDoesntHave('remittanceLines', function ($q): void {
                $q->whereHas('remittance', function ($r): void {
                    $r->whereIn('status', ['draft', 'submitted', 'validated']);
                });
            });

        if (! empty($validated['from'])) {
            $query->whereDate('paid_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('paid_at', '<=', $validated['to']);
        }

        $sales = $query->orderBy('paid_at')->get();

        $ticketsCount = $sales->count();
        $grossAmount = (int) $sales->sum('gross_amount');
        $commissionAmount = (int) $sales->sum('commission_amount');
        $netAmount = (int) $sales->sum('outstanding_amount');
        $saleUuids = $sales->pluck('uuid')->all();

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $validated['from'] ?? now()->toDateString(),
                'to' => $validated['to'] ?? now()->toDateString(),
                'tickets_count' => $ticketsCount,
                'gross_amount' => $grossAmount,
                'commission_amount' => $commissionAmount,
                'net_amount' => $netAmount,
                'sale_uuids' => $saleUuids,
            ],
        ]);
    }

    /**
     * Declare a partner remittance for selected unsettled sales of a period with proof document.
     */
    public function declareSettlement(Request $request): RedirectResponse
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        $validated = $request->validate([
            'sale_uuids' => ['required', 'array', 'min:1', 'max:500'],
            'sale_uuids.*' => ['required', 'uuid'],
            'method' => ['required', 'in:bank_transfer,bank_deposit,check,mobile_money,cash'],
            'reference' => ['required', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:102400'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var list<string> $saleUuids */
        $saleUuids = array_values(array_map('strval', (array) $validated['sale_uuids']));
        $proof = $request->file('proof');
        if (! $proof instanceof UploadedFile) {
            throw ValidationException::withMessages(['proof' => 'La preuve de versement est obligatoire.']);
        }

        $this->declareRemittance->execute(
            $user,
            $saleUuids,
            (string) $validated['method'],
            (string) $validated['reference'],
            $proof,
            $validated['bank_name'] ?? null,
            $validated['notes'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Versement déclaré avec succès. Vos fonds sont en attente de vérification par la Trésorerie KORA.',
        ]);

        return redirect()->route('kora-point.settlements');
    }

    /**
     * Display passenger refunds list and allow refund requests.
     */
    public function refunds(Request $request): Response
    {
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);

        return Inertia::render('kora-points/agent/refunds', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $this->agentRead->health($point),
            'refunds' => Inertia::defer(fn (): array => $this->agentRead->refunds($point, $user)),
            'sales' => Inertia::defer(fn (): array => $this->agentRead->sales($point, $user)),
        ]);
    }
}
