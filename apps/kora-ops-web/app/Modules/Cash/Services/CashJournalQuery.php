<?php

namespace App\Modules\Cash\Services;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CashJournalQuery
{
    public const CHANNELS = ['cash' => 'Espèces', 'mtn_momo' => 'MTN MoMo', 'moov_flooz' => 'Moov Money / Flooz', 'celtiis_cash' => 'Celtiis Cash'];

    public const TYPES = [
        'counter_sale' => 'Vente guichet',
        'counter_sale_cash' => 'Vente guichet espèces',
        'counter_sale_mobile_money' => 'Vente guichet Mobile Money',
        'parcel_sale_cash' => 'Envoi colis espèces',
        'parcel_sale_mobile_money' => 'Envoi colis Mobile Money',
        'mobile_money_payment' => 'Paiement Mobile Money',
        'cash_payment' => 'Paiement espèces',
        'reversement' => 'Reversement',
        'expense' => 'Dépense',
        'adjustment' => 'Ajustement',
    ];

    public const STATUSES = ['posted' => 'Publié', 'pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté', 'reversed' => 'Contrepassé'];

    public const SESSION_STATUSES = ['open' => 'Ouverte', 'pending_approval' => 'En attente d’approbation', 'closed' => 'Clôturée'];

    public const NATURES = ['all', 'movements', 'lifecycle'];

    public const LIFECYCLE_EVENTS = ['session_opened' => 'Ouverture de caisse', 'session_submitted' => 'Soumission pour approbation', 'session_closed' => 'Clôture de caisse', 'session_force_closed' => 'Clôture forcée', 'variance_approved' => 'Écart approuvé'];

    /**
     * Session "courante" de l'agent : celle du jour, ou la dernière encore
     * ouverte quelle que soit sa date (session zombie à clôturer en retard).
     */
    public function session(User $user): ?CashSession
    {
        return CashSession::query()
            ->where('agent_id', $user->getKey())
            ->where('agency_id', $user->agency_id)
            ->where(function (Builder $query): void {
                $query->whereDate('business_date', now()->toDateString())
                    ->orWhere('status', CashSessionStatus::Open->value);
            })
            ->orderByRaw('case when status = ? then 0 else 1 end', [CashSessionStatus::Open->value])
            ->latest('business_date')
            ->latest('id')
            ->first()
            ?->load($this->sessionRelations());
    }

    /**
     * Scope des sessions pour le journal unifié selon les permissions :
     * réseau (tout) -> agence (toutes les sessions de l'agence) -> personnel (ses propres sessions).
     *
     * @param  array{from?: string, to?: string, agent?: string, session_uuid?: string}  $filters
     * @return Builder<CashSession>
     */
    public function journalScopedSessions(User $user, array $filters = []): Builder
    {
        $query = CashSession::query()
            ->latest('business_date')
            ->latest('id');

        if ($user->can('cash.view-network')) {
            if (! empty($filters['agent'])) {
                $query->whereHas('agent', fn (Builder $agent) => $agent->where('uuid', $filters['agent']));
            }
        } elseif ($user->agency_id !== null && $user->can('cash.view-agency')) {
            $query->where('agency_id', $user->agency_id);
            if (! empty($filters['agent'])) {
                $query->whereHas('agent', fn (Builder $agent) => $agent->where('uuid', $filters['agent']));
            }
        } else {
            $query->where('agent_id', $user->getKey());
        }

        if (! empty($filters['from'])) {
            $query->whereDate('business_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('business_date', '<=', $filters['to']);
        }
        if (! empty($filters['session_uuid'])) {
            $query->where('uuid', $filters['session_uuid']);
        }

        return $query;
    }

    /**
     * @param  Builder<CashSession>  $sessions
     * @param  array{q: string, channel: string, status: string, direction: string}  $filters
     * @return Builder<CashMovement>
     */
    public function movementsForSessions(Builder $sessions, array $filters): Builder
    {
        $sessionIds = (clone $sessions)->reorder()->select('cash_sessions.id');
        $query = CashMovement::query()->whereIn('cash_session_id', $sessionIds);

        return $this->applyMovementFilters($query, $filters);
    }

    /**
     * Charge les relations nécessaires à la sérialisation complète d'une
     * session (détail partagé entre la vue agent et la supervision).
     */
    public function loadRelations(CashSession $session): CashSession
    {
        return $session->loadMissing($this->sessionRelations());
    }

    /**
     * @param  array{q: string, channel: string, status: string, direction: string}  $filters
     * @return Builder<CashMovement>
     */
    public function movements(?CashSession $session, array $filters): Builder
    {
        $query = CashMovement::query()->where('cash_session_id', $session?->getKey() ?? 0);

        return $this->applyMovementFilters($query, $filters);
    }

    /**
     * Journal unifié d'un ensemble de sessions : mouvements financiers
     * et événements de cycle de vie (ouverture, soumission, clôture, approbation).
     *
     * @param  Builder<CashSession>  $sessions  Scope de sessions déjà autorisé
     * @param  array{q: string, channel: string, status: string, direction: string, nature?: string}  $filters
     */
    public function operations(Builder $sessions, array $filters): QueryBuilder
    {
        $sessionIds = (clone $sessions)->reorder()->select('cash_sessions.id');
        $nature = $filters['nature'] ?? 'all';
        $legs = [];

        if ($nature !== 'lifecycle') {
            $legs[] = $this->movementOperationLeg($sessionIds);
        }

        if ($nature !== 'movements') {
            $legs = [...$legs, ...$this->lifecycleOperationLegs($sessionIds)];
        }

        $union = array_shift($legs);
        foreach ($legs as $leg) {
            $union->unionAll($leg);
        }

        $operations = DB::query()->fromSub($union, 'operations');

        if ($filters['q'] !== '') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $labelled = [
                ...array_keys(array_filter(self::TYPES, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q'])))),
                ...array_keys(array_filter(self::LIFECYCLE_EVENTS, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q'])))),
            ];
            $operations->where(function (QueryBuilder $query) use ($term, $labelled): void {
                $query->whereRaw('LOWER(reference) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(destination_label) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(agent_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(channel) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(event) LIKE ?', [$term])
                    ->orWhereIn('event', $labelled);
            });
        }
        if ($filters['channel'] !== 'all') {
            $operations->where('channel', $filters['channel']);
        }
        foreach (['status', 'direction'] as $field) {
            if ($filters[$field] !== 'all') {
                $operations->where($field, $filters[$field]);
            }
        }

        return $operations
            ->orderByDesc('occurred_at')
            ->orderBy('uuid');
    }

    /**
     * @param  Builder<CashSession>  $sessionIds
     * @return Builder<CashMovement>
     */
    private function movementOperationLeg(Builder $sessionIds): Builder
    {
        $query = CashMovement::query()
            ->whereIn('cash_movements.cash_session_id', $sessionIds)
            ->whereNotNull('cash_movements.occurred_at')
            ->join('cash_sessions', 'cash_sessions.id', '=', 'cash_movements.cash_session_id')
            ->join('users', 'users.id', '=', 'cash_sessions.agent_id')
            ->leftJoin('cash_ledgers', 'cash_ledgers.id', '=', 'cash_movements.cash_ledger_id')
            ->leftJoin('agency_payment_accounts', 'agency_payment_accounts.id', '=', 'cash_ledgers.agency_payment_account_id');

        return $query->select([
            'cash_movements.uuid',
            DB::raw("'movement' as kind"),
            'cash_movements.type as event',
            'cash_movements.direction',
            'cash_movements.status',
            'cash_movements.amount',
            'cash_movements.reference',
            'cash_movements.destination_label',
            'cash_movements.occurred_at',
            DB::raw('coalesce(agency_payment_accounts.network, cash_ledgers.kind) as channel'),
            'cash_sessions.uuid as session_uuid',
            'cash_sessions.business_date',
            'users.name as agent_name',
            'cash_ledgers.kind as ledger_kind',
            'agency_payment_accounts.network as account_network',
        ]);
    }

    /**
     * @param  Builder<CashSession>  $sessionIds
     * @return list<Builder<CashSession>>
     */
    private function lifecycleOperationLegs(Builder $sessionIds): array
    {
        $opened = $this->lifecycleLeg(
            $sessionIds,
            "'session_opened'",
            'cash_sessions.opened_at',
            'session_agent.name',
            'cash_sessions.opened_at',
        );
        $submitted = $this->lifecycleLeg(
            $sessionIds,
            "'session_submitted'",
            'cash_sessions.submitted_at',
            'session_agent.name',
            'cash_sessions.submitted_at',
        );
        $closed = $this->lifecycleLeg(
            $sessionIds,
            "case when cash_sessions.force_closed_at is not null then 'session_force_closed' else 'session_closed' end",
            'coalesce(cash_sessions.force_closed_at, cash_sessions.closed_at)',
            'coalesce(session_closer.name, session_agent.name)',
            'cash_sessions.closed_at',
            forcedClose: true,
        );
        $approved = $this->lifecycleLeg(
            $sessionIds,
            "'variance_approved'",
            'cash_sessions.approved_at',
            'coalesce(session_approver.name, session_agent.name)',
            'cash_sessions.approved_at',
            approver: true,
        );

        return [$opened, $submitted, $closed, $approved];
    }

    /**
     * @param  Builder<CashSession>  $sessionIds
     * @param  literal-string  $eventExpression
     * @param  literal-string  $timeExpression
     * @param  literal-string  $actorExpression
     * @param  literal-string  $notNullColumn
     * @return Builder<CashSession>
     */
    private function lifecycleLeg(
        Builder $sessionIds,
        string $eventExpression,
        string $timeExpression,
        string $actorExpression,
        string $notNullColumn,
        bool $forcedClose = false,
        bool $approver = false,
    ): Builder {
        $query = CashSession::query()
            ->whereIn('cash_sessions.id', $sessionIds)
            ->join('users as session_agent', 'session_agent.id', '=', 'cash_sessions.agent_id');

        if ($forcedClose) {
            $query->where(fn (Builder $query) => $query->whereNotNull('cash_sessions.closed_at')->orWhereNotNull('cash_sessions.force_closed_at'))
                ->leftJoin('users as session_closer', 'session_closer.id', '=', 'cash_sessions.force_closed_by');
        } else {
            $query->whereNotNull($notNullColumn);
        }

        if ($approver) {
            $query->leftJoin('users as session_approver', 'session_approver.id', '=', 'cash_sessions.approved_by');
        }

        return $query->select([
            'cash_sessions.uuid',
            DB::raw("'lifecycle' as kind"),
            DB::raw("{$eventExpression} as event"),
            DB::raw('NULL as direction'),
            DB::raw('NULL as status'),
            DB::raw('NULL as amount'),
            DB::raw('NULL as reference'),
            DB::raw('NULL as destination_label'),
            DB::raw("{$timeExpression} as occurred_at"),
            DB::raw('NULL as channel'),
            'cash_sessions.uuid as session_uuid',
            'cash_sessions.business_date',
            DB::raw("{$actorExpression} as agent_name"),
            DB::raw('NULL as ledger_kind'),
            DB::raw('NULL as account_network'),
        ]);
    }

    /**
     * @param  Builder<CashMovement>  $query
     * @param  array{q: string, channel: string, status: string, direction: string}  $filters
     * @return Builder<CashMovement>
     */
    private function applyMovementFilters(Builder $query, array $filters): Builder
    {
        if ($filters['q'] !== '') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $types = array_keys(array_filter(self::TYPES, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q']))));
            $channels = array_keys(array_filter(self::CHANNELS, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($filters['q']))));
            $query->where(function (Builder $query) use ($term, $types, $channels): void {
                $query->whereRaw('LOWER(reference) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(destination_label) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(type) LIKE ?', [$term])
                    ->orWhereIn('type', $types)
                    ->orWhereHas('cashLedger', function (Builder $ledger) use ($channels): void {
                        $ledger->whereHas('agencyPaymentAccount', fn (Builder $account) => $account->whereIn('network', $channels));
                        if (in_array('cash', $channels, true)) {
                            $ledger->orWhere('kind', 'cash');
                        }
                    });
            });
        }
        if ($filters['channel'] !== 'all') {
            $query->whereHas('cashLedger', function (Builder $ledger) use ($filters): void {
                if ($filters['channel'] === 'cash') {
                    $ledger->where('kind', 'cash');
                } else {
                    $ledger->whereHas('agencyPaymentAccount', fn (Builder $account) => $account->where('network', $filters['channel']));
                }
            });
        }
        foreach (['status', 'direction'] as $field) {
            if ($filters[$field] !== 'all') {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }

    /** @return array<array-key, mixed> */
    private function sessionRelations(): array
    {
        return [
            'agent',
            'agency',
            'cashRegister',
            'approvedBy',
            'forceClosedBy',
            'ledgers' => fn ($query) => $query
                ->with(['agencyPaymentAccount', 'media'])
                ->withCount('movements')
                ->withSum(['movements as inflows' => fn ($movements) => $movements->whereIn('status', ['posted', 'approved'])->where('direction', 'in')], 'amount')
                ->withSum(['movements as outflows' => fn ($movements) => $movements->whereIn('status', ['posted', 'approved'])->where('direction', 'out')], 'amount'),
        ];
    }
}
