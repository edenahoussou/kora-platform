<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Services\CashJournalQuery;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class KoraPointAgentReadService
{
    public function __construct(
        private readonly KoraPointAccess $access,
        private readonly KoraPointTermsService $terms,
        private readonly KoraPointLedgerService $ledger,
        private readonly CashJournalQuery $cashJournal,
    ) {}

    /**
     * Resolve the active KoraPoint assigned to the given agent user.
     *
     *
     * @throws AuthorizationException
     */
    public function resolvePoint(User $agent): KoraPoint
    {
        if ($agent->status !== UserStatus::Active || ! $agent->hasRole('kora_point_agent') || $agent->agency_id === null) {
            throw new AuthorizationException('Accès réservé aux agents de guichet KORA POINT actifs.');
        }

        /** @var KoraPoint|null $point */
        $point = KoraPoint::query()
            ->where('agency_id', $agent->agency_id)
            ->with(['agency', 'organization.media', 'media'])
            ->first();

        if ($point === null) {
            throw new AuthorizationException('Aucun KORA POINT associé à cette agence.');
        }

        if ($point->status !== 'active') {
            throw new AuthorizationException('Ce KORA POINT est actuellement inactif.');
        }

        return $point;
    }

    /**
     * Build the agent point profile representation matching KoraPointProfile.
     *
     * @return array<string, mixed>
     */
    public function profile(KoraPoint $point, User $agent): array
    {
        $point->loadMissing(['agency', 'organization.media', 'media']);
        $terms = $this->terms->resolve($point);
        $schedule = $terms['settlement_calendar'] ?? ['frequency' => 'weekly', 'time' => '17:00', 'timezone' => 'Africa/Porto-Novo', 'weekday' => 5];

        $status = 'ready';
        $health = $this->health($point);
        if ($health['is_overdue']) {
            $status = 'overdue';
        } elseif ($health['current_exposure'] >= $health['credit_limit'] && $health['credit_limit'] > 0) {
            $status = 'limit_reached';
        } elseif ($health['usage_percentage'] >= 80) {
            $status = 'warning_threshold';
        }

        return [
            'uuid' => $point->uuid,
            'code' => $point->agency->code,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'address' => $point->address ?? $point->agency->city,
            'manager_name' => $point->responsible_name ?? $agent->name,
            'manager_phone' => $point->phone ?? '',
            'manager_email' => $agent->email ?? '',
            'status' => $status,
            'logo_url' => $point->getFirstMediaUrl('logo', 'thumb') ?: null,
            'organization' => $point->organization ? [
                'uuid' => $point->organization->uuid,
                'code' => $point->organization->code,
                'name' => $point->organization->name,
                'logo_url' => $point->organization->getFirstMediaUrl('logo', 'thumb') ?: null,
            ] : null,
            'terms' => [
                'commission_rate_bps' => (int) ($terms['commission_basis_points'] ?? 0),
                'credit_limit_amount' => (int) ($terms['ceiling_amount'] ?? 0),
                'settlement_schedule' => [
                    'frequency' => $schedule['frequency'] ?? 'weekly',
                    'day_of_week' => $schedule['weekday'] ?? 5,
                    'day_of_month' => $schedule['day'] ?? 1,
                    'time' => $schedule['time'] ?? '17:00',
                    'timezone' => $schedule['timezone'] ?? 'Africa/Porto-Novo',
                ],
                'grace_period_days' => $this->terms->graceDays(),
            ],
        ];
    }

    /**
     * Compute the financial health snapshot for the point.
     *
     * @return array<string, mixed>
     */
    public function health(KoraPoint $point): array
    {
        $terms = $this->terms->resolve($point);
        $creditLimit = (int) ($terms['ceiling_amount'] ?? 0);
        $exposure = $this->ledger->balance($point);
        $available = max(0, $creditLimit - $exposure);
        $usagePct = $creditLimit > 0 ? (int) min(100, round(($exposure / $creditLimit) * 100)) : 0;

        $graceDays = $this->terms->graceDays();
        $dueLimit = now()->subDays($graceDays);

        $isOverdue = $point->sales()
            ->where('outstanding_amount', '>', 0)
            ->where('due_at', '<=', $dueLimit)
            ->exists();

        $salesAllowed = ! $isOverdue && ($creditLimit <= 0 || $exposure < $creditLimit);

        $blockReason = null;
        if ($isOverdue) {
            $blockReason = 'Reversement en retard de plus de '.$graceDays.' jours. Régularisez votre dette pour débloquer les ventes.';
        } elseif ($creditLimit > 0 && $exposure >= $creditLimit) {
            $blockReason = 'Plafond de crédit atteint ('.number_format($creditLimit, 0, ',', ' ').' FCFA). Déclarez un versement pour débloquer les ventes.';
        }

        $oldestDueSale = $point->sales()
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_at')
            ->first();

        $nextDueDate = $oldestDueSale?->due_at?->toISOString() ?? now()->addDays(7)->toISOString();
        $nextDueLabel = $oldestDueSale !== null
            ? ($oldestDueSale->due_at->isPast() ? 'Échue '.$oldestDueSale->due_at->diffForHumans() : 'Échéance '.$oldestDueSale->due_at->diffForHumans())
            : 'Aucune échéance immédiate';

        return [
            'credit_limit' => $creditLimit,
            'current_exposure' => $exposure,
            'available_balance' => $available,
            'usage_percentage' => $usagePct,
            'next_due_date' => $nextDueDate,
            'next_due_label' => $nextDueLabel,
            'is_overdue' => $isOverdue,
            'sales_allowed' => $salesAllowed,
            'block_reason' => $blockReason,
        ];
    }

    /**
     * Compute daily metrics and 7-day sales activity.
     *
     * @return array<string, mixed>
     */
    public function metrics(KoraPoint $point): array
    {
        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = CarbonImmutable::now()->endOfDay();

        $todaySales = $point->sales()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->get();

        $todaySalesAmount = (int) $todaySales->sum('gross_amount');
        $todayTicketsCount = (int) $todaySales->count();
        $todayCommissionAmount = (int) $todaySales->sum('commission_amount');

        $totalDueAmount = $this->ledger->balance($point);

        $activity7d = [];
        $dayNames = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];

        for ($i = 6; $i >= 0; $i--) {
            $date = CarbonImmutable::now()->subDays($i);
            $start = $date->startOfDay();
            $end = $date->endOfDay();

            $daySales = $point->sales()
                ->whereBetween('created_at', [$start, $end])
                ->get();

            $activity7d[] = [
                'date' => $date->toDateString(),
                'day_short' => $dayNames[$date->isoWeekday()] ?? $date->format('D'),
                'sales_amount' => (int) $daySales->sum('gross_amount'),
                'commission_amount' => (int) $daySales->sum('commission_amount'),
                'tickets_count' => (int) $daySales->count(),
            ];
        }

        return [
            'today_sales_amount' => $todaySalesAmount,
            'today_tickets_count' => $todayTicketsCount,
            'today_commission_amount' => $todayCommissionAmount,
            'total_due_amount' => $totalDueAmount,
            'activity_7d' => $activity7d,
        ];
    }

    /**
     * Build the operational priority alerts list for the agent dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function priorities(KoraPoint $point): array
    {
        $alerts = [];
        $health = $this->health($point);

        if ($health['is_overdue']) {
            $alerts[] = [
                'id' => 'alert-overdue',
                'type' => 'OVERDUE_SETTLEMENT',
                'title' => 'Reversement en retard critique',
                'description' => 'Un ou plusieurs décomptes dépassent le délai de grâce accordé. Les ventes sont temporairement bloquées.',
                'amount' => $health['current_exposure'],
                'urgency' => 'CRITICAL',
                'action_label' => 'Déclarer un versement',
                'action_target' => 'settlements',
            ];
        } elseif ($health['current_exposure'] >= $health['credit_limit'] && $health['credit_limit'] > 0) {
            $alerts[] = [
                'id' => 'alert-limit',
                'type' => 'LIMIT_REACHED',
                'title' => 'Plafond d’encours atteint (100 %)',
                'description' => 'Votre encours a atteint le plafond autorisé. Déclarez un versement pour réactiver les émissions de billets.',
                'amount' => $health['current_exposure'],
                'urgency' => 'HIGH',
                'action_label' => 'Effectuer un versement',
                'action_target' => 'settlements',
            ];
        } elseif ($health['usage_percentage'] >= 80) {
            $alerts[] = [
                'id' => 'alert-warning-80',
                'type' => 'WARNING_80',
                'title' => 'Alerte seuil d’encours ('.$health['usage_percentage'].' %)',
                'description' => 'Vous approchez du plafond de crédit. Anticipez votre déclaration de versement avant blocage.',
                'amount' => $health['current_exposure'],
                'urgency' => 'MEDIUM',
                'action_label' => 'Voir les versements',
                'action_target' => 'settlements',
            ];
        }

        $pendingRemittancesCount = KoraPointRemittance::query()
            ->whereHas('lines.sale', fn ($q) => $q->where('point_id', $point->getKey()))
            ->where('status', 'submitted')
            ->count();

        if ($pendingRemittancesCount > 0) {
            $alerts[] = [
                'id' => 'alert-remittance-pending',
                'type' => 'SETTLEMENT_PENDING_APPROVAL',
                'title' => $pendingRemittancesCount.' bordereau(x) en attente de validation',
                'description' => 'Votre versement a bien été déclaré et est en cours de contrôle par l’équipe Finance KORA.',
                'urgency' => 'LOW',
                'action_label' => 'Suivre l’état',
                'action_target' => 'settlements',
            ];
        }

        return $alerts;
    }

    /**
     * Build the agent's current cash session status.
     *
     * @return array<string, mixed>
     */
    public function cashSession(User $agent): array
    {
        /** @var CashSession|null $session */
        $session = $this->cashJournal->session($agent);

        if ($session === null || $session->status !== CashSessionStatus::Open) {
            return [
                'status' => 'CLOSED',
                'opened_at' => 'Session fermée',
                'operator_name' => $agent->name,
                'opening_amount' => 0,
                'cash_collected' => 0,
                'mobile_money_collected' => 0,
                'expected_closing_cash' => 0,
                'movements' => [],
            ];
        }

        $movements = [];
        $session->loadMissing('movements');
        /** @var CashMovement $movement */
        foreach ($session->movements as $movement) {
            $movements[] = [
                'id' => $movement->uuid,
                'time_label' => $movement->created_at ? $movement->created_at->format('H:i') : '08:00',
                'label' => $movement->type,
                'amount' => (int) $movement->amount,
                'type' => $movement->type,
                'method' => 'CASH',
                'balance_after' => (int) $movement->amount,
            ];
        }

        $openingAmount = (int) $session->opening_float;
        $totalCash = (int) $session->total_cash;
        $totalMobileMoney = (int) $session->total_mobile_money;

        return [
            'status' => 'OPEN',
            'opened_at' => $session->opened_at ? 'Aujourd’hui à '.$session->opened_at->format('H:i') : 'En cours',
            'closed_at' => $session->closed_at?->toISOString(),
            'operator_name' => $agent->name,
            'opening_amount' => $openingAmount,
            'cash_collected' => $totalCash,
            'mobile_money_collected' => $totalMobileMoney,
            'expected_closing_cash' => $openingAmount + $totalCash,
            'movements' => $movements,
        ];
    }

    /**
     * Build the cash and mobile money brouillard for KORA POINT partner with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dailyCashBrouillard(KoraPoint $point, User $agent, array $filters = []): array
    {
        $today = now()->toDateString();
        $from = ! empty($filters['from']) ? (string) $filters['from'] : $today;
        $to = ! empty($filters['to']) ? (string) $filters['to'] : $today;
        $channel = ! empty($filters['channel']) ? (string) $filters['channel'] : 'all';
        $q = ! empty($filters['q']) ? trim((string) $filters['q']) : '';

        $query = KoraPointSale::query()
            ->where('point_id', $point->getKey())
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->with(['payment', 'reservation.passengers'])
            ->orderByDesc('paid_at');

        if ($channel === 'cash') {
            $query->whereHas('payment', fn ($p) => $p->where('method', 'cash'));
        } elseif ($channel === 'mobile_money') {
            $query->whereHas('payment', fn ($p) => $p->where('method', 'mobile_money'));
        } elseif ($channel !== 'all') {
            $query->whereHas('payment', fn ($p) => $p->where('provider', $channel));
        }

        if ($q !== '') {
            $term = '%'.mb_strtolower($q).'%';
            $query->where(function ($sub) use ($term) {
                $sub->whereHas('reservation.passengers', function ($pQuery) use ($term) {
                    $pQuery->whereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                })
                    ->orWhereHas('reservation', function ($rQuery) use ($term) {
                        $rQuery->whereRaw('LOWER(CAST(uuid AS TEXT)) LIKE ?', [$term]);
                    })
                    ->orWhereHas('payment', function ($pmQuery) use ($term) {
                        $pmQuery->whereRaw('LOWER(payer_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(payer_phone) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(provider_reference) LIKE ?', [$term]);
                    });
            });
        }

        $sales = $query->get();

        $grossTotal = (int) $sales->sum('gross_amount');
        $commissionTotal = (int) $sales->sum('commission_amount');
        $netTotal = (int) $sales->sum('net_amount');

        $cashTotal = 0;
        $mobileMoneyTotal = 0;

        $movements = [];
        foreach ($sales as $sale) {
            $payment = $sale->payment;
            $method = $payment?->method->value ?? 'cash';
            if ($method === 'cash') {
                $cashTotal += (int) $sale->gross_amount;
            } else {
                $mobileMoneyTotal += (int) $sale->gross_amount;
            }

            $passenger = $sale->reservation?->passengers->first();
            $passengerName = $passenger !== null
                ? trim("{$passenger->first_name} {$passenger->last_name}")
                : ($payment !== null ? $payment->payer_name : 'Client comptoir');

            $networkLabel = $payment?->provider;
            if ($payment !== null && $payment->mobile_money_network instanceof MobileMoneyNetwork) {
                $networkLabel = $payment->mobile_money_network->label();
            }

            $dateLabel = $sale->paid_at->format('d/m/Y');
            $timeLabel = $sale->paid_at->format('H:i');
            $ticketRef = $sale->reservation !== null
                ? ($sale->reservation->reference ?? strtoupper(substr($sale->reservation->uuid, 0, 8)))
                : 'BILLET';
            $passengerPhone = $passenger !== null && $passenger->phone !== null && $passenger->phone !== ''
                ? $passenger->phone
                : ($payment !== null && $payment->payer_phone !== null && $payment->payer_phone !== '' ? $payment->payer_phone : '—');
            $reference = $payment !== null && $payment->provider_reference !== null && $payment->provider_reference !== ''
                ? $payment->provider_reference
                : '—';

            $movements[] = [
                'id' => (string) $sale->getKey(),
                'date_label' => $dateLabel,
                'time_label' => $timeLabel,
                'ticket_reference' => $ticketRef,
                'passenger_name' => $passengerName,
                'passenger_phone' => $passengerPhone,
                'method' => $method,
                'network' => $payment?->provider,
                'network_label' => $networkLabel,
                'reference' => $reference,
                'gross_amount' => (int) $sale->gross_amount,
                'commission_amount' => (int) $sale->commission_amount,
                'net_amount' => (int) $sale->net_amount,
            ];
        }

        if ($from === $to) {
            $periodLabel = $from === $today
                ? 'Aujourd’hui · '.CarbonImmutable::now()->settings(['locale' => 'fr'])->isoFormat('dddd D MMMM YYYY')
                : CarbonImmutable::parse($from)->settings(['locale' => 'fr'])->isoFormat('dddd D MMMM YYYY');
        } else {
            $periodLabel = 'Du '.CarbonImmutable::parse($from)->settings(['locale' => 'fr'])->isoFormat('D MMM YYYY').' au '.CarbonImmutable::parse($to)->settings(['locale' => 'fr'])->isoFormat('D MMM YYYY');
        }

        return [
            'date_label' => $periodLabel,
            'gross_total' => $grossTotal,
            'commission_total' => $commissionTotal,
            'net_total' => $netTotal,
            'cash_total' => $cashTotal,
            'mobile_money_total' => $mobileMoneyTotal,
            'tickets_count' => $sales->count(),
            'movements' => $movements,
        ];
    }

    /**
     * Retrieve paginated sales tickets for the point.
     *
     * @return array<string, mixed>
     */
    public function sales(KoraPoint $point, User $agent, int $perPage = 30): array
    {
        $paginator = $this->access->sales($agent)
            ->where('point_id', $point->getKey())
            ->with([
                'reservation.tickets',
                'reservation.passengers',
                'reservation.segments.departure.line',
                'payment',
            ])
            ->latest('id')
            ->paginate($perPage);

        /** @var array<int, array<string, mixed>> $items */
        $items = $paginator->getCollection()->map(fn (KoraPointSale $sale): array => $this->serializeSale($sale))->values()->all();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Retrieve paginated commission entries for the point.
     *
     * @return array<string, mixed>
     */
    public function commissions(KoraPoint $point, User $agent, string $period = 'current_month', int $perPage = 30): array
    {
        $query = $this->access->sales($agent)
            ->where('point_id', $point->getKey())
            ->when($period === 'current_month', fn (Builder $sales): Builder => $sales->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]))
            ->when($period === 'last_month', function (Builder $sales): Builder {
                $lastMonth = now()->subMonthNoOverflow();

                return $sales->whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()]);
            });
        $totals = [
            'gross' => (int) (clone $query)->sum('gross_amount'),
            'initial' => (int) (clone $query)->sum('commission_amount'),
            'corrections' => (int) (clone $query)->sum('commission_reversed'),
            'net' => (int) (clone $query)->selectRaw('COALESCE(SUM(commission_amount - commission_reversed), 0) as total')->value('total'),
        ];
        $count = (clone $query)->count();
        $paginator = $query
            ->with([
                'reservation.tickets',
                'reservation.segments.departure.line',
            ])
            ->latest('id')
            ->paginate($perPage);

        /** @var array<int, array<string, mixed>> $items */
        $items = $paginator->getCollection()->map(function (KoraPointSale $sale): array {
            $ticket = $sale->reservation->tickets->first();
            $departure = $sale->reservation->primaryDeparture();
            $line = $departure?->line;

            $routeLabel = $line !== null ? $line->name : 'Trajet standard';

            $terms = $sale->terms_snapshot;
            $bps = (int) ($terms['commission_basis_points'] ?? 300);
            $initialCommission = (int) $sale->commission_amount;
            $reversed = (int) $sale->commission_reversed;
            $netCommission = $initialCommission - $reversed;

            return [
                'uuid' => $sale->uuid,
                'sale_uuid' => $sale->uuid,
                'ticket_number' => $ticket !== null ? $ticket->short_code : 'TK-'.strtoupper(substr($sale->uuid, 0, 8)),
                'date_label' => $sale->created_at ? $sale->created_at->diffForHumans() : 'Récemment',
                'departure_ref' => $line !== null ? $line->code : 'Ligne standard',
                'route_label' => $routeLabel,
                'seller_name' => $sale->point->responsible_name ?? 'Agent',
                'amount_paid' => (int) $sale->gross_amount,
                'commission_rate_bps' => $bps,
                'initial_commission' => $initialCommission,
                'correction_amount' => -$reversed,
                'net_commission' => $netCommission,
                'settlement_status' => $sale->status === 'settled' ? 'settled' : 'open',
                'has_refund_correction' => $reversed > 0,
                'refund_reason' => $reversed > 0 ? 'Annulation / Remboursement' : null,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'totals' => $totals,
            'period' => $period,
            'period_label' => match ($period) {
                'last_month' => now()->subMonthNoOverflow()->translatedFormat('F Y'),
                'all' => 'Toutes les périodes',
                default => now()->translatedFormat('F Y').' (En cours)',
            },
            'count' => $count,
        ];
    }

    /**
     * Retrieve paginated remittances (settlements) for the point.
     *
     * @return array<string, mixed>
     */
    public function settlements(KoraPoint $point, User $agent, int $perPage = 30): array
    {
        $saleIds = $this->access->sales($agent)->where('point_id', $point->getKey())->select('id');

        $paginator = KoraPointRemittance::query()
            ->whereHas('lines', fn ($q) => $q->whereIn('sale_id', $saleIds))
            ->with(['lines.sale'])
            ->latest('id')
            ->paginate($perPage);

        $statusMap = [
            'draft' => 'DRAFT',
            'submitted' => 'DECLARED',
            'validated' => 'VALIDATED',
            'rejected' => 'REJECTED',
            'partially_validated' => 'PARTIALLY_VALIDATED',
        ];

        /** @var array<int, array<string, mixed>> $items */
        $items = $paginator->getCollection()->map(function (KoraPointRemittance $remittance) use ($point, $statusMap): array {
            $ticketsCovered = $remittance->lines->map(fn ($line): string => 'TK-'.strtoupper(substr($line->sale ? $line->sale->uuid : '', 0, 8)))->all();

            $status = $statusMap[$remittance->status] ?? 'DECLARED';
            $method = match ($remittance->method) {
                'bank_transfer' => 'BANK_TRANSFER',
                'bank_deposit' => 'BANK_DEPOSIT',
                'check' => 'CHECK',
                default => 'MOBILE_MONEY',
            };

            return [
                'uuid' => $remittance->uuid,
                'reference' => $remittance->reference ?? 'BORD-'.strtoupper(substr($remittance->uuid, 0, 8)),
                'point_uuid' => $point->uuid,
                'point_name' => $point->agency->name,
                'created_by_name' => 'Agent de guichet',
                'period_start' => $remittance->created_at ? $remittance->created_at->toDateString() : '',
                'period_end' => $remittance->submitted_at ? $remittance->submitted_at->toDateString() : ($remittance->created_at ? $remittance->created_at->toDateString() : ''),
                'created_at' => $remittance->created_at ? $remittance->created_at->diffForHumans() : '',
                'declared_at' => $remittance->submitted_at?->toISOString(),
                'validated_at' => $remittance->validated_at?->toISOString(),
                'amount_expected' => (int) $remittance->amount,
                'amount_declared' => (int) $remittance->amount,
                'amount_validated' => $remittance->received_amount,
                'payment_method' => $method,
                'payment_reference' => $remittance->reference ?? '',
                'status' => $status,
                'sales_count' => $remittance->lines->count(),
                'tickets_covered' => $ticketsCovered,
                'notes' => $remittance->note,
                'timeline' => [
                    [
                        'step' => 'Création',
                        'date_label' => $remittance->created_at ? $remittance->created_at->diffForHumans() : '',
                        'actor' => 'Agent',
                    ],
                ],
            ];
        })->values()->all();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Retrieve paginated refunds for the point.
     *
     * @return array<string, mixed>
     */
    public function refunds(KoraPoint $point, User $agent, int $perPage = 30): array
    {
        $saleIds = $this->access->sales($agent)->where('point_id', $point->getKey())->select('id');

        $paginator = KoraPointRefund::query()
            ->whereIn('sale_id', $saleIds)
            ->with(['sale.reservation.tickets', 'sale.reservation.segments.departure.line'])
            ->latest('id')
            ->paginate($perPage);

        $statusMap = [
            'requested' => 'PENDING_APPROVAL',
            'authorized' => 'APPROVED',
            'executed' => 'RESTITUTED',
            'confirmed' => 'RESTITUTED',
            'rejected' => 'REJECTED',
        ];

        /** @var array<int, array<string, mixed>> $items */
        $items = $paginator->getCollection()->map(function (KoraPointRefund $refund) use ($statusMap): array {
            $ticket = $refund->sale->reservation->tickets->first();
            $departure = $refund->sale->reservation->primaryDeparture();
            $line = $departure?->line;

            $status = $statusMap[$refund->status] ?? 'PENDING_APPROVAL';

            return [
                'uuid' => $refund->uuid,
                'reference' => 'REM-'.strtoupper(substr($refund->uuid, 0, 8)),
                'ticket_number' => $ticket !== null ? $ticket->short_code : 'TK-'.strtoupper(substr($refund->sale->uuid, 0, 8)),
                'sale_uuid' => $refund->sale->uuid,
                'passenger_name' => $refund->beneficiary ?? 'Passager',
                'passenger_phone' => '',
                'departure_ref' => $line !== null ? $line->code : 'Ligne standard',
                'route_label' => $line !== null ? $line->name : 'Trajet standard',
                'original_amount' => (int) $refund->sale->gross_amount,
                'retained_fee' => 0,
                'refund_amount' => (int) ($refund->amount ?? $refund->sale->gross_amount),
                'commission_clawback' => (int) $refund->sale->commission_reversed,
                'reason' => 'CUSTOMER_REQUEST',
                'reason_label' => $refund->reason ?? 'Demande voyageur',
                'status' => $status,
                'requested_at' => $refund->created_at ? $refund->created_at->diffForHumans() : '',
                'requested_by' => 'Agent',
                'approved_at' => $refund->executed_at?->toISOString(),
                'restituted_at' => $refund->confirmed_at?->toISOString(),
                'receipt_signed' => $refund->status === 'confirmed',
                'timeline' => [
                    [
                        'step' => 'Demande',
                        'date_label' => $refund->created_at ? $refund->created_at->diffForHumans() : '',
                        'actor' => 'Agent',
                    ],
                ],
            ];
        })->values()->all();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Retrieve partner convention, legal documents, and assigned team members.
     *
     * @return array<string, mixed>
     */
    public function profileDetails(KoraPoint $point, User $agent): array
    {
        $terms = $this->terms->resolve($point);

        $convention = [
            'ref' => 'CONV-KP-'.$point->agency->code,
            'title' => 'Convention de partenariat commercial — Distribution Billetterie Bus',
            'signed_at' => $point->created_at ? $point->created_at->format('d/m/Y') : now()->format('d/m/Y'),
            'expires_at' => $point->created_at ? $point->created_at->addYear()->format('d/m/Y') : now()->addYear()->format('d/m/Y'),
            'status' => $point->status === 'active' ? 'ACTIVE' : 'EXPIRED',
            'file_name' => 'convention-partenariat-'.$point->agency->code.'.pdf',
            'file_size' => '245 Ko',
            'commission_rate_bps' => (int) ($terms['commission_basis_points'] ?? 0),
            'credit_limit_amount' => (int) ($terms['ceiling_amount'] ?? 0),
        ];

        $documents = KoraPartnerDocument::query()
            ->where('point_id', $point->getKey())
            ->latest('id')
            ->get()
            ->map(fn (KoraPartnerDocument $doc): array => [
                'uuid' => $doc->uuid,
                'title' => $doc->title,
                'category' => match ($doc->kind) {
                    'rccm' => 'REGISTRE',
                    'ifu' => 'FISCAL',
                    'id_card' => 'IDENTITE',
                    'lease' => 'LOCALISATION',
                    default => 'AUTRE',
                },
                'file_name' => $doc->reference ?? $doc->title.'.pdf',
                'file_size' => '150 Ko',
                'uploaded_at' => $doc->created_at ? $doc->created_at->format('d/m/Y') : '',
                'status' => 'VALID',
            ])->values()->all();

        $assignedUsers = [
            [
                'uuid' => $agent->uuid,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $point->phone ?? '',
                'role_label' => 'Guichetier principal',
                'assigned_at' => $agent->created_at ? $agent->created_at->format('d/m/Y') : '',
                'is_primary' => true,
            ],
        ];

        return [
            'convention' => $convention,
            'documents' => $documents,
            'assignedUsers' => $assignedUsers,
        ];
    }

    /**
     * Serialize a KoraPointSale into a PointSaleRecord structure.
     *
     * @return array<string, mixed>
     */
    private function serializeSale(KoraPointSale $sale): array
    {
        $ticket = $sale->reservation->tickets->first();
        $passenger = $sale->reservation->passengers->first();
        $departure = $sale->reservation->primaryDeparture();
        $line = $departure?->line;

        $routeLabel = $line !== null ? $line->name : 'Trajet standard';

        $terms = $sale->terms_snapshot;
        $bps = (int) ($terms['commission_basis_points'] ?? 300);

        return [
            'uuid' => $sale->uuid,
            'ticket_number' => $ticket !== null ? $ticket->short_code : 'TK-'.strtoupper(substr($sale->uuid, 0, 8)),
            'short_code' => $ticket !== null ? $ticket->short_code : strtoupper(substr($sale->uuid, 0, 8)),
            'created_at' => $sale->created_at ? $sale->created_at->toISOString() : '',
            'date_label' => $sale->created_at ? $sale->created_at->diffForHumans() : '',
            'seller_name' => $sale->point->responsible_name ?? 'Agent',
            'departure_ref' => $line !== null ? $line->code : 'Ligne standard',
            'service_class' => 'Standard',
            'departure_time' => $departure && $departure->departure_time ? $departure->departure_time : '08:00',
            'route_label' => $routeLabel,
            'origin_city' => $sale->point->agency->city,
            'destination_city' => 'Destination',
            'seat_number' => '12',
            'passenger_name' => $passenger ? ($passenger->first_name.' '.$passenger->last_name) : 'Voyageur',
            'passenger_phone' => $passenger && $passenger->phone ? $passenger->phone : '',
            'payment_method' => $sale->payment->method === PaymentMethod::Cash ? 'CASH' : 'MOBILE_MONEY',
            'amount_paid' => (int) $sale->gross_amount,
            'commission_rate_bps' => $bps,
            'commission_amount' => (int) $sale->commission_amount,
            'settlement_status' => $sale->status === 'settled' ? 'settled' : 'open',
            'refundable' => $sale->status !== 'settled' && $sale->refunded_amount < $sale->gross_amount,
        ];
    }
}
