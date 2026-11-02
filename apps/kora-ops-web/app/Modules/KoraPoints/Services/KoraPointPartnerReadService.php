<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class KoraPointPartnerReadService
{
    public function __construct(
        private readonly KoraPointAccess $access,
        private readonly KoraPointReadService $pointRead,
        private readonly KoraPointTermsService $terms,
    ) {}

    /**
     * Builds the organization dashboard from the same point and sale data used by the agent workspace.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $actor, KoraPointOrganization $organization, ?string $pointUuid = null): array
    {
        $pointQuery = $this->pointRead->query($actor)->where('organization_id', $organization->getKey());
        $allOrganizationPoints = (clone $pointQuery)->orderBy('id')->get();
        if ($pointUuid !== null) {
            $pointQuery->where('uuid', $pointUuid);
        }

        $pointModels = $pointQuery->orderBy('id')->get();
        $pointItems = $allOrganizationPoints->map(fn (KoraPoint $point): array => $this->pointRead->point($point, $actor))->all();

        $organizationAllocation = $this->pointRead->allocatedCreditLimit($organization);
        $selectedAllocation = (int) $pointModels->sum(function (KoraPoint $point): int {
            $exposure = max(0, (int) $point->getAttribute('debt_total'));

            return $point->status === 'closed' && $exposure === 0
                ? 0
                : max(0, (int) ($point->ceiling_amount ?? 0));
        });
        $exposure = (int) $pointModels->sum(fn (KoraPoint $point): int => max(0, (int) $point->getAttribute('debt_total')));
        $activePoints = $pointModels->where('status', 'active');
        $graceDays = $this->terms->graceDays();
        $dueBefore = now()->subDays($graceDays);
        $overduePointIds = $pointModels->pluck('id')->all();
        $overdueByPoint = KoraPointSale::query()
            ->whereIn('point_id', $overduePointIds)
            ->where('outstanding_amount', '>', 0)
            ->where('due_at', '<=', $dueBefore)
            ->select('point_id')
            ->distinct()
            ->pluck('point_id')
            ->map(fn (mixed $pointId): int => (int) $pointId)
            ->values()
            ->all();

        $blockedActiveCount = $activePoints->filter(function (KoraPoint $point) use ($overdueByPoint): bool {
            $limit = (int) ($this->terms->resolve($point)['ceiling_amount'] ?? 0);

            return in_array($point->getKey(), $overdueByPoint, true)
                || ($limit > 0 && (int) $point->getAttribute('debt_total') >= $limit);
        })->count();

        $sales = $this->organizationSalesQuery($actor, $organization);
        if ($pointUuid !== null) {
            $sales->whereHas('point', fn (Builder $points): Builder => $points->where('uuid', $pointUuid));
        }

        $today = CarbonImmutable::now();
        $todaySales = (clone $sales)->whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()]);
        $activity = [];
        $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $daily = (clone $sales)->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()]);
            $activity[] = [
                'date' => $day->toDateString(),
                'day_short' => $dayNames[$day->isoWeekday()] ?? $day->format('D'),
                'sales_amount' => (int) (clone $daily)->sum('gross_amount'),
                'commission_amount' => (int) (clone $daily)->sum('commission_amount'),
                'tickets_count' => (int) (clone $daily)->count(),
            ];
        }

        $selectedPoint = $pointUuid === null ? null : $pointModels->first();
        $profile = $selectedPoint !== null
            ? $this->pointProfile($selectedPoint, $organization, $actor)
            : $this->organizationProfile($organization, $actor);
        $priorities = $this->priorities($pointModels->values()->all(), $overdueByPoint);

        return [
            'profile' => $profile,
            'health' => [
                'credit_limit' => $selectedAllocation,
                'current_exposure' => $exposure,
                'available_balance' => max(0, $selectedAllocation - $exposure),
                'usage_percentage' => $selectedAllocation > 0 ? (int) min(100, round(($exposure / $selectedAllocation) * 100)) : 0,
                'next_due_date' => now()->addDays(7)->toIso8601String(),
                'next_due_label' => count($overdueByPoint) > 0 ? count($overdueByPoint).' Point(s) en retard' : 'Aucune échéance dépassée',
                'is_overdue' => count($overdueByPoint) > 0,
                'sales_allowed' => $activePoints->isNotEmpty() && $blockedActiveCount < $activePoints->count(),
                'block_reason' => $blockedActiveCount === $activePoints->count() && $activePoints->isNotEmpty()
                    ? 'Tous les Points actifs sont bloqués par leur encours ou une échéance dépassée.'
                    : null,
                'global_credit_limit' => (int) $organization->ceiling_amount,
                'organization_allocated_limit' => $organizationAllocation,
                'unallocated_amount' => max(0, (int) $organization->ceiling_amount - $organizationAllocation),
                'network_point_count' => $activePoints->count(),
                'blocked_point_count' => $blockedActiveCount,
            ],
            'metrics' => [
                'today_sales_amount' => (int) (clone $todaySales)->sum('gross_amount'),
                'today_tickets_count' => (int) (clone $todaySales)->count(),
                'today_commission_amount' => (int) (clone $todaySales)->sum('commission_amount'),
                'total_due_amount' => $exposure,
                'activity_7d' => $activity,
            ],
            'priorities' => $priorities,
            'points' => $pointItems,
            'point_filter_options' => $allOrganizationPoints->map(fn (KoraPoint $point): array => [
                'uuid' => $point->uuid,
                'name' => $point->agency->name,
                'code' => $point->agency->code,
                'status' => $point->status,
            ])->values()->all(),
            'selected_point_uuid' => $pointUuid,
        ];
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function sales(User $actor, KoraPointOrganization $organization, ?string $pointUuid = null, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->organizationSalesQuery($actor, $organization)
            ->with([
                'point.agency',
                'reservation.tickets.reservationPassenger',
                'reservation.passengers',
                'reservation.segments.seat',
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.departure.line',
                'payment',
            ])
            ->latest('id');
        $this->applyPointFilter($query, $pointUuid);

        return $query->paginate($perPage)->withQueryString()->through(fn (KoraPointSale $sale): array => $this->ticket($sale));
    }

    /**
     * @return array{sales: LengthAwarePaginator<int, array<string, mixed>>, totals: array{gross: int, initial: int, corrections: int, net: int}, period: string, period_label: string, count: int}
     */
    public function commissions(User $actor, KoraPointOrganization $organization, string $period, ?string $pointUuid = null, int $perPage = 30): array
    {
        $query = $this->commissionQuery($actor, $organization, $period, $pointUuid);
        $totals = [
            'gross' => (int) (clone $query)->sum('gross_amount'),
            'initial' => (int) (clone $query)->sum('commission_amount'),
            'corrections' => (int) (clone $query)->sum('commission_reversed'),
            'net' => (int) (clone $query)->selectRaw('COALESCE(SUM(commission_amount - commission_reversed), 0) as total')->value('total'),
        ];

        $count = (clone $query)->count();
        $sales = $query->with(['point.agency', 'reservation.tickets', 'reservation.segments.departure.line'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (KoraPointSale $sale): array => $this->commission($sale));

        return [
            'sales' => $sales,
            'totals' => $totals,
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'count' => $count,
        ];
    }

    /** @return iterable<array<int, string|int|float|null>> */
    public function commissionExportRows(User $actor, KoraPointOrganization $organization, string $period, ?string $pointUuid = null): iterable
    {
        $query = $this->commissionQuery($actor, $organization, $period, $pointUuid)
            ->with(['point.agency', 'reservation.tickets'])
            ->orderBy('created_at');

        foreach ($query->lazy(300) as $sale) {
            yield [
                $sale->created_at?->format('Y-m-d H:i:s'),
                $sale->point->agency->name,
                $sale->point->agency->code,
                $sale->reservation->tickets->first()->short_code,
                $sale->gross_amount,
                $sale->commission_amount,
                $sale->commission_reversed,
                (int) $sale->commission_amount - (int) $sale->commission_reversed,
                $sale->status,
            ];
        }
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function refunds(User $actor, KoraPointOrganization $organization, ?string $pointUuid = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->refundsQuery($actor, $organization, $pointUuid)
            ->with(['sale.point.agency', 'sale.reservation.tickets', 'sale.reservation.passengers'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (KoraPointRefund $refund): array => $this->refund($refund));
    }

    /** @return array{pending: int, authorized: int, completed: int, total_refunded: int, commission_clawback: int, count: int} */
    public function refundSummary(User $actor, KoraPointOrganization $organization, ?string $pointUuid = null): array
    {
        $refunds = $this->refundsQuery($actor, $organization, $pointUuid);

        return [
            'pending' => (clone $refunds)->where('status', 'requested')->count(),
            'authorized' => (clone $refunds)->where('status', 'authorized')->count(),
            'completed' => (clone $refunds)->whereIn('status', ['executed', 'confirmed'])->count(),
            'total_refunded' => (int) (clone $refunds)->whereIn('status', ['executed', 'confirmed'])->sum('amount'),
            'commission_clawback' => (int) KoraPointSale::query()
                ->whereIn('id', (clone $refunds)->select('sale_id')->distinct())
                ->sum('commission_reversed'),
            'count' => (clone $refunds)->count(),
        ];
    }

    public function periodLabel(string $period): string
    {
        return match ($period) {
            'last_month' => now()->subMonthNoOverflow()->translatedFormat('F Y'),
            'all' => 'Toutes les périodes',
            default => now()->translatedFormat('F Y').' (En cours)',
        };
    }

    /** @return Builder<KoraPointSale> */
    private function organizationSalesQuery(User $actor, KoraPointOrganization $organization): Builder
    {
        return $this->access->sales($actor)->where('organization_id', $organization->getKey());
    }

    /** @return Builder<KoraPointSale> */
    private function commissionQuery(User $actor, KoraPointOrganization $organization, string $period, ?string $pointUuid): Builder
    {
        $query = $this->organizationSalesQuery($actor, $organization);
        $this->applyPointFilter($query, $pointUuid);

        if ($period === 'current_month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'last_month') {
            $lastMonth = now()->subMonthNoOverflow();
            $query->whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()]);
        }

        return $query;
    }

    /** @return Builder<KoraPointRefund> */
    private function refundsQuery(User $actor, KoraPointOrganization $organization, ?string $pointUuid): Builder
    {
        $query = KoraPointRefund::query()
            ->whereIn('sale_id', $this->organizationSalesQuery($actor, $organization)->select('id'));
        if ($pointUuid !== null) {
            $query->whereHas('sale.point', fn (Builder $points): Builder => $points->where('uuid', $pointUuid));
        }

        return $query;
    }

    /** @param Builder<KoraPointSale> $query */
    private function applyPointFilter(Builder $query, ?string $pointUuid): void
    {
        if ($pointUuid !== null) {
            $query->whereHas('point', fn (Builder $points): Builder => $points->where('uuid', $pointUuid));
        }
    }

    /** @return array<string, mixed> */
    private function ticket(KoraPointSale $sale): array
    {
        $ticket = $sale->reservation->tickets->first();
        $passenger = $ticket->reservationPassenger ?? $sale->reservation->passengers->first();
        $segment = $sale->reservation->segments->first();
        $departure = $segment->departure ?? $sale->reservation->primaryDeparture();
        $line = $departure?->line;
        $origin = $segment->originStop->name ?? $sale->point->agency->city;
        $destination = $segment->destinationStop->name ?? 'Destination non renseignée';
        $terms = $sale->terms_snapshot;

        return [
            'uuid' => $sale->uuid,
            'ticket_number' => $ticket->short_code ?? 'TK-'.strtoupper(substr($sale->uuid, 0, 8)),
            'short_code' => $ticket->short_code ?? strtoupper(substr($sale->uuid, 0, 8)),
            'created_at' => $sale->created_at?->toIso8601String() ?? '',
            'date_label' => $sale->created_at?->diffForHumans() ?? '',
            'seller_name' => $sale->point->responsible_name ?? 'Agent',
            'departure_ref' => $line->code ?? '—',
            'service_class' => 'Bus',
            'departure_time' => $departure->departure_time ?? '—',
            'route_label' => $line->name ?? $origin.' → '.$destination,
            'origin_city' => $origin,
            'destination_city' => $destination,
            'seat_number' => $segment?->seat->code ?? '—',
            'passenger_name' => $passenger !== null ? trim($passenger->first_name.' '.$passenger->last_name) : 'Voyageur',
            'passenger_phone' => $passenger->phone ?? '',
            'payment_method' => $sale->payment?->method?->value === 'cash' ? 'CASH' : 'MOBILE_MONEY',
            'amount_paid' => (int) $sale->gross_amount,
            'commission_rate_bps' => (int) ($terms['commission_basis_points'] ?? 0),
            'commission_amount' => (int) $sale->commission_amount,
            'settlement_status' => $sale->status === 'settled' ? 'settled' : 'open',
            'refundable' => false,
            'point_uuid' => $sale->point->uuid,
            'point_name' => $sale->point->agency->name,
            'point_code' => $sale->point->agency->code,
        ];
    }

    /** @return array<string, mixed> */
    private function commission(KoraPointSale $sale): array
    {
        $ticket = $sale->reservation->tickets->first();
        $reversed = (int) $sale->commission_reversed;

        return [
            'uuid' => $sale->uuid,
            'sale_uuid' => $sale->uuid,
            'ticket_number' => $ticket->short_code ?? 'TK-'.strtoupper(substr($sale->uuid, 0, 8)),
            'date_label' => $sale->created_at?->diffForHumans() ?? '',
            'departure_ref' => $sale->point->agency->code,
            'route_label' => $sale->point->agency->name,
            'seller_name' => $sale->point->agency->code,
            'amount_paid' => (int) $sale->gross_amount,
            'commission_rate_bps' => (int) ($sale->terms_snapshot['commission_basis_points'] ?? 0),
            'initial_commission' => (int) $sale->commission_amount,
            'correction_amount' => -$reversed,
            'net_commission' => (int) $sale->commission_amount - $reversed,
            'settlement_status' => $sale->status === 'settled' ? 'settled' : 'open',
            'has_refund_correction' => $reversed > 0,
            'refund_reason' => $reversed > 0 ? 'Annulation / remboursement' : null,
            'point_uuid' => $sale->point->uuid,
            'point_name' => $sale->point->agency->name,
            'point_code' => $sale->point->agency->code,
        ];
    }

    /** @return array<string, mixed> */
    private function refund(KoraPointRefund $refund): array
    {
        $sale = $refund->sale;
        $ticket = $sale->reservation->tickets->first();
        $passenger = $sale->reservation->passengers->first();

        return [
            'uuid' => $refund->uuid,
            'reference' => 'REM-'.strtoupper(substr($refund->uuid, 0, 8)),
            'ticket_number' => $ticket->short_code ?? 'TK-'.strtoupper(substr($sale->uuid, 0, 8)),
            'sale_uuid' => $sale->uuid,
            'passenger_name' => $passenger !== null ? trim($passenger->first_name.' '.$passenger->last_name) : 'Voyageur',
            'passenger_phone' => '',
            'departure_ref' => $sale->point->agency->code,
            'route_label' => $sale->point->agency->name,
            'original_amount' => (int) $sale->gross_amount,
            'retained_fee' => 0,
            'refund_amount' => (int) ($refund->amount ?? $sale->gross_amount),
            'commission_clawback' => (int) $sale->commission_reversed,
            'reason' => 'TRIP_CANCELLED',
            'reason_label' => $refund->reason ?? 'Demande de remboursement',
            'status' => match ($refund->status) {
                'authorized' => 'APPROVED_BY_FINANCE',
                'executed', 'confirmed' => 'FUNDS_RESTITUTED',
                'rejected' => 'REJECTED',
                default => 'REQUESTED',
            },
            'requested_at' => $refund->created_at?->diffForHumans() ?? '',
            'requested_by' => 'Équipe du Point',
            'approved_at' => $refund->executed_at?->toIso8601String(),
            'restituted_at' => $refund->confirmed_at?->toIso8601String(),
            'receipt_signed' => $refund->status === 'confirmed',
            'rejection_reason' => $refund->status === 'rejected' ? $refund->reason : null,
            'timeline' => [],
            'point_uuid' => $sale->point->uuid,
            'point_name' => $sale->point->agency->name,
            'point_code' => $sale->point->agency->code,
        ];
    }

    /** @param array<int, KoraPoint> $points
     * @param  array<int, int>  $overduePointIds
     * @return list<array<string, mixed>>
     */
    private function priorities(array $points, array $overduePointIds): array
    {
        $alerts = [];
        foreach ($points as $point) {
            $exposure = max(0, (int) $point->getAttribute('debt_total'));
            $limit = (int) ($this->terms->resolve($point)['ceiling_amount'] ?? 0);
            $usage = $limit > 0 ? (int) min(100, round(($exposure / $limit) * 100)) : 0;

            if ($point->status === 'suspended' || $point->status === 'closed') {
                $alerts[] = [
                    'id' => 'point-'.$point->uuid.'-status',
                    'level' => 'info',
                    'title' => $point->agency->name.' · '.($point->status === 'closed' ? 'clôturé' : 'suspendu'),
                    'description' => 'Ce Point n’est pas actuellement ouvert à la vente.',
                    'time_label' => $point->agency->code,
                    'action_label' => 'Voir le Point',
                    'action_target' => 'points',
                ];

                continue;
            }

            if (in_array($point->getKey(), $overduePointIds, true)) {
                $alerts[] = [
                    'id' => 'point-'.$point->uuid.'-overdue',
                    'level' => 'critical',
                    'title' => $point->agency->name.' · reversement en retard',
                    'description' => 'Encours : '.number_format($exposure, 0, ',', ' ').' FCFA. Les ventes de ce Point sont bloquées.',
                    'time_label' => $point->agency->code,
                    'action_label' => 'Voir les reversements',
                    'action_target' => 'remittances',
                ];
            } elseif ($limit > 0 && $usage >= 100) {
                $alerts[] = [
                    'id' => 'point-'.$point->uuid.'-limit',
                    'level' => 'critical',
                    'title' => $point->agency->name.' · plafond atteint',
                    'description' => 'Encours : '.number_format($exposure, 0, ',', ' ').' FCFA sur '.number_format($limit, 0, ',', ' ').' FCFA.',
                    'time_label' => $point->agency->code,
                    'action_label' => 'Voir les reversements',
                    'action_target' => 'remittances',
                ];
            } elseif ($limit > 0 && $usage >= 80) {
                $alerts[] = [
                    'id' => 'point-'.$point->uuid.'-threshold',
                    'level' => 'warning',
                    'title' => $point->agency->name.' · seuil d’encours',
                    'description' => 'Le Point utilise '.$usage.' % de son allocation.',
                    'time_label' => $point->agency->code,
                    'action_label' => 'Voir le Point',
                    'action_target' => 'points',
                ];
            }
        }

        return array_slice($alerts, 0, 8);
    }

    /** @return array<string, mixed> */
    private function organizationProfile(KoraPointOrganization $organization, User $actor): array
    {
        return [
            'uuid' => $organization->uuid,
            'code' => $organization->code,
            'name' => $organization->name,
            'city' => '',
            'country' => $organization->country,
            'address' => $organization->address ?? '',
            'manager_name' => $actor->name,
            'manager_phone' => $organization->phone ?? '',
            'manager_email' => $actor->email,
            'status' => 'ready',
            'logo_url' => $organization->getFirstMediaUrl('logo', 'thumb') ?: null,
            'organization' => null,
            'terms' => [
                'commission_rate_bps' => (int) $organization->commission_basis_points,
                'credit_limit_amount' => (int) $organization->ceiling_amount,
                'settlement_schedule' => ['frequency' => $organization->settlement_calendar['frequency'] ?? 'daily', 'time' => $organization->settlement_calendar['time'] ?? '17:00', 'timezone' => $organization->settlement_calendar['timezone'] ?? 'Africa/Porto-Novo'],
                'grace_period_days' => $this->terms->graceDays(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pointProfile(KoraPoint $point, KoraPointOrganization $organization, User $actor): array
    {
        return [
            'uuid' => $point->uuid,
            'code' => $point->agency->code,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'address' => $point->address ?? $point->agency->city,
            'manager_name' => $actor->name,
            'manager_phone' => $point->phone ?? '',
            'manager_email' => $actor->email,
            'status' => 'ready',
            'logo_url' => $point->getFirstMediaUrl('logo', 'thumb') ?: null,
            'organization' => ['uuid' => $organization->uuid, 'code' => $organization->code, 'name' => $organization->name, 'logo_url' => $organization->getFirstMediaUrl('logo', 'thumb') ?: null],
            'terms' => [
                'commission_rate_bps' => (int) ($point->commission_basis_points ?? $organization->commission_basis_points),
                'credit_limit_amount' => (int) ($point->ceiling_amount ?? 0),
                'settlement_schedule' => ['frequency' => $point->settlement_calendar['frequency'] ?? $organization->settlement_calendar['frequency'] ?? 'daily', 'time' => $point->settlement_calendar['time'] ?? $organization->settlement_calendar['time'] ?? '17:00', 'timezone' => $point->settlement_calendar['timezone'] ?? $organization->settlement_calendar['timezone'] ?? 'Africa/Porto-Novo'],
                'grace_period_days' => $this->terms->graceDays(),
            ],
        ];
    }
}
