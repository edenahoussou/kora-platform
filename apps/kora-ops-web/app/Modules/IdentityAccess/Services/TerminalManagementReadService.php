<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\AppDistribution\Services\TerminalAppUpdateResolver;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class TerminalManagementReadService
{
    private const ONLINE_MINUTES = 15;

    private const OFFLINE_HOURS = 24;

    /** @var list<string> */
    private const HISTORY_TYPES = [
        'terminal_registered',
        'terminal_assigned',
        'terminal_unassigned',
        'terminal_activated',
        'terminal_deactivated',
        'terminal_maintenance_set',
        'terminal_lost',
        'terminal_recovered',
        'terminal_returned_to_stock',
        'terminal_scan',
        'terminal_print',
        'terminal_error',
        'terminal_handshake',
        'terminal_sync',
        'ops_session_opened',
        'ops_session_revoked',
        'terminal_assignment',
    ];

    public function __construct(private TerminalAppUpdateResolver $appUpdates) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = $this->filteredQuery($request);

        return $query
            ->orderBy('serial_number')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TerminalP501 $terminal): array => $this->serializeSummary($terminal));
    }

    /**
     * @return array{total: int, active: int, online: int, attention: int}
     */
    public function statistics(Request $request): array
    {
        $base = $this->baseQuery($request);
        $now = now();

        $active = (clone $base)->where('status', TerminalP501Status::Active->value)->count();
        $online = (clone $base)->whereHas('sessions', fn (Builder $query): Builder => $this->onlineSessionQuery($query, $now))->count();
        $attention = (clone $base)
            ->where(function (Builder $query) use ($now): void {
                $query->where('status', '!=', TerminalP501Status::Active->value)
                    ->orWhere(function (Builder $activeQuery) use ($now): void {
                        $activeQuery
                            ->where('status', TerminalP501Status::Active->value)
                            ->whereDoesntHave('sessions', fn (Builder $sessionQuery): Builder => $this->onlineSessionQuery($sessionQuery, $now));
                    });
            })
            ->count();

        return [
            'total' => (clone $base)->count(),
            'active' => $active,
            'online' => $online,
            'attention' => $attention,
        ];
    }

    /**
     * @return array{statuses: array<string, string>, agencies: array<int, array{uuid: string, code: string, name: string}>}
     */
    public function references(): array
    {
        return [
            'statuses' => TerminalP501Status::options(),
            'agencies' => Agency::query()
                ->orderBy('name')
                ->get(['uuid', 'code', 'name'])
                ->map(fn (Agency $agency): array => [
                    'uuid' => $agency->uuid,
                    'code' => $agency->code,
                    'name' => $agency->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function historyTypes(): array
    {
        return array_map(fn (string $type): array => [
            'value' => $type,
            'label' => $this->eventLabel($type),
        ], self::HISTORY_TYPES);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSummary(TerminalP501 $terminal): array
    {
        $now = now();
        $lastSeenAt = $this->carbonAttribute($terminal, 'last_seen_at');
        $lastActiveSeenAt = $this->carbonAttribute($terminal, 'last_active_seen_at');
        $activeSessionsCount = (int) ($terminal->active_sessions_count ?? 0);

        return [
            'uuid' => $terminal->uuid,
            'serial_number' => $terminal->serial_number,
            'type' => $terminal->type->value,
            'hardware_model' => $terminal->hardware_model,
            'os_version' => $terminal->os_version,
            'app_version' => $terminal->app_version,
            'version_status' => $this->versionStatus($terminal),
            'status' => $terminal->status->value,
            'connectivity_status' => $this->connectivityStatus($terminal, $now, $lastSeenAt, $lastActiveSeenAt, $activeSessionsCount),
            'assigned_user' => $terminal->relationLoaded('assignedUser') && $terminal->assignedUser ? [
                'uuid' => $terminal->assignedUser->uuid,
                'name' => $terminal->assignedUser->name,
            ] : null,
            'agency' => $terminal->relationLoaded('agency') && $terminal->agency ? [
                'uuid' => $terminal->agency->uuid,
                'code' => $terminal->agency->code,
                'name' => $terminal->agency->name,
            ] : null,
            'last_seen_at' => $lastSeenAt?->toISOString(),
            'last_activity_at' => $this->carbonAttribute($terminal, 'last_activity_at')?->toISOString(),
            'last_sync_at' => $terminal->last_sync_at?->toISOString(),
            'last_handshake_at' => $terminal->last_handshake_at?->toISOString(),
            'active_sessions_count' => $activeSessionsCount,
            'created_at' => $terminal->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(TerminalP501 $terminal): array
    {
        $terminal->load([
            'assignedUser:id,uuid,name',
            'agency:id,uuid,code,name',
            'assignments' => fn ($query) => $query
                ->with(['assignedUser:id,uuid,name', 'agency:id,uuid,code,name', 'assignedByUser:id,uuid,name'])
                ->latest('assigned_at'),
        ]);

        $activeSessions = $terminal->sessions()
            ->active()
            ->with('user:id,uuid,name')
            ->latest('last_seen_at')
            ->get(['uuid', 'terminal_p501_id', 'user_id', 'started_at', 'last_seen_at', 'expires_at', 'absolute_expires_at', 'renewed_at', 'renewal_count', 'revoked_at', 'revoked_reason'])
            ->filter(fn (TerminalSession $session): bool => $this->isLiveSession($session))
            ->values();

        $terminal->setAttribute('last_seen_at', $this->parseDatabaseTimestamp(
            $terminal->sessions()->orderByDesc('last_seen_at')->value('last_seen_at'),
        ));
        $terminal->setAttribute('last_active_seen_at', $activeSessions
            ->map(fn (TerminalSession $session): ?CarbonInterface => $this->sessionTimestamp($session, 'last_seen_at'))
            ->filter()
            ->sort()
            ->last());
        $terminal->setAttribute('active_sessions_count', $activeSessions->count());

        return [
            'terminal' => $this->serializeSummary($terminal),
            'assignment_history' => $terminal->assignments->map(fn (TerminalAssignment $assignment): array => [
                'uuid' => $assignment->uuid,
                'assigned_user' => $assignment->assignedUser ? [
                    'uuid' => $assignment->assignedUser->uuid,
                    'name' => $assignment->assignedUser->name,
                ] : null,
                'agency' => $assignment->agency ? [
                    'uuid' => $assignment->agency->uuid,
                    'code' => $assignment->agency->code,
                    'name' => $assignment->agency->name,
                ] : null,
                'assigned_by' => $assignment->assignedByUser ? [
                    'uuid' => $assignment->assignedByUser->uuid,
                    'name' => $assignment->assignedByUser->name,
                ] : null,
                'assigned_at' => $assignment->assigned_at->toISOString(),
                'unassigned_at' => $assignment->unassigned_at?->toISOString(),
                'reason' => $assignment->reason,
            ])->values()->all(),
            'sessions' => $activeSessions->map(fn (TerminalSession $session): array => [
                'uuid' => $session->uuid,
                'user' => [
                    'uuid' => $session->user->uuid,
                    'name' => $session->user->name,
                ],
                'started_at' => $session->started_at->toISOString(),
                'last_seen_at' => $session->last_seen_at?->toISOString(),
                'expires_at' => $session->expires_at?->toISOString(),
                'absolute_expires_at' => $session->absolute_expires_at?->toISOString(),
                'renewed_at' => $session->renewed_at?->toISOString(),
                'renewal_count' => $session->renewal_count,
            ])->values()->all(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function history(TerminalP501 $terminal, Request $request): LengthAwarePaginator
    {
        $activityQuery = Activity::query()
            ->where('subject_type', TerminalP501::class)
            ->where('subject_id', $terminal->id)
            ->selectRaw("'activity' as source, id as source_id, created_at as occurred_at, description as event_type, causer_id as actor_id");

        $assignmentQuery = TerminalAssignment::query()
            ->where('terminal_p501_id', $terminal->id)
            ->selectRaw("'assignment' as source, id as source_id, assigned_at as occurred_at, 'terminal_assignment' as event_type, assigned_by as actor_id");

        $sessionQuery = TerminalSession::query()
            ->where('terminal_p501_id', $terminal->id)
            ->selectRaw("'session' as source, id as source_id, started_at as occurred_at, 'ops_session_opened' as event_type, user_id as actor_id");

        $eventQuery = $activityQuery
            ->unionAll($assignmentQuery)
            ->unionAll($sessionQuery);

        $historyQuery = DB::query()->fromSub($eventQuery, 'terminal_history');
        $eventType = $request->string('event_type')->trim()->value();

        if ($eventType !== '' && in_array($eventType, self::HISTORY_TYPES, true)) {
            $historyQuery->where('event_type', $eventType);
        }

        $from = $this->historyDate($request->string('from')->trim()->value(), false);
        if ($from !== null) {
            $historyQuery->where('occurred_at', '>=', $from);
        }

        $to = $this->historyDate($request->string('to')->trim()->value(), true);
        if ($to !== null) {
            $historyQuery->where('occurred_at', '<=', $to);
        }

        $history = $historyQuery
            ->orderByDesc('occurred_at')
            ->paginate(20, ['*'], 'history_page')
            ->withQueryString();

        $events = $history->getCollection();
        $activityIds = $events
            ->where('source', 'activity')
            ->pluck('source_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
        $assignmentIds = $events
            ->where('source', 'assignment')
            ->pluck('source_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
        $actorIds = $events
            ->pluck('actor_id')
            ->filter(fn (mixed $id): bool => (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $activities = Activity::query()
            ->whereIn('id', $activityIds)
            ->get()
            ->keyBy('id');
        $assignments = TerminalAssignment::query()
            ->with(['assignedUser:id,uuid,name', 'agency:id,uuid,code,name'])
            ->whereIn('id', $assignmentIds)
            ->get()
            ->keyBy('id');
        $actors = User::query()
            ->whereIn('id', $actorIds)
            ->get(['id', 'uuid', 'name'])
            ->keyBy('id');

        return $history->through(fn (object $event): array => $this->serializeHistoryEvent($event, $activities, $assignments, $actors));
    }

    /**
     * @return Builder<TerminalP501>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);
        $now = now();

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $query->whereRaw('LOWER(serial_number) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $status = $request->string('status')->trim()->value();
        if (in_array($status, TerminalP501Status::values(), true)) {
            $query->where('status', $status);
        }

        $agencyUuid = $request->string('agency')->trim()->value();
        if ($agencyUuid !== '') {
            $query->whereIn('agency_id', Agency::query()->where('uuid', $agencyUuid)->select('id'));
        }

        $assignment = $request->string('assignment')->trim()->value();
        if ($assignment === 'assigned') {
            $query->where(function (Builder $builder): void {
                $builder->whereNotNull('assigned_user_id')->orWhereNotNull('agency_id');
            });
        } elseif ($assignment === 'unassigned') {
            $query->whereNull('assigned_user_id')->whereNull('agency_id');
        }

        $health = $request->string('health')->trim()->value();
        if ($health === 'online') {
            $query->whereHas('sessions', fn (Builder $builder): Builder => $this->onlineSessionQuery($builder, $now));
        } elseif ($health === 'monitor') {
            $query
                ->whereDoesntHave('sessions', fn (Builder $builder): Builder => $this->onlineSessionQuery($builder, $now))
                ->whereHas('sessions', fn (Builder $builder): Builder => $builder
                    ->whereNotNull('last_seen_at')
                    ->whereBetween('last_seen_at', [$now->copy()->subHours(self::OFFLINE_HOURS), $now->copy()->subMinutes(self::ONLINE_MINUTES)]));
        } elseif ($health === 'offline') {
            $query->whereDoesntHave('sessions', fn (Builder $builder): Builder => $builder
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', $now->copy()->subHours(self::OFFLINE_HOURS)));
        }

        return $query;
    }

    /**
     * @return Builder<TerminalP501>
     */
    private function baseQuery(Request $request): Builder
    {
        $now = now();
        $table = (new TerminalP501)->getTable();

        return TerminalP501::query()
            ->where('type', TerminalType::P501->value)
            ->with(['assignedUser:id,uuid,name', 'agency:id,uuid,code,name'])
            ->withCount([
                'sessions as active_sessions_count' => fn (Builder $query): Builder => $query
                    ->whereNull('revoked_at')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', $now),
            ])
            ->withMax([
                'sessions as last_seen_at' => fn (Builder $query): Builder => $query,
            ], 'last_seen_at')
            ->withMax([
                'sessions as last_active_seen_at' => fn (Builder $query): Builder => $query
                    ->whereNull('revoked_at')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', $now),
            ], 'last_seen_at')
            ->addSelect([
                'last_activity_at' => Activity::query()
                    ->select('created_at')
                    ->where('subject_type', TerminalP501::class)
                    ->whereColumn('subject_id', $table.'.id')
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->withCasts([
                'last_seen_at' => 'datetime',
                'last_active_seen_at' => 'datetime',
                'last_activity_at' => 'datetime',
            ]);
    }

    /**
     * @param  Builder<TerminalSession>  $query
     * @return Builder<TerminalSession>
     */
    private function onlineSessionQuery(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(self::ONLINE_MINUTES));
    }

    /**
     * Statut de version de l'app installée (MEZ-150), calculé côté serveur
     * selon la même règle que le bloc `update` renvoyé au terminal.
     *
     * @return array{status: string, label: string, latest_version: string|null, latest_build: int|null, mandatory: bool}
     */
    private function versionStatus(TerminalP501 $terminal): array
    {
        $check = $this->appUpdates->check($terminal);

        return [
            'status' => $check->status->value,
            'label' => $check->status->label(),
            'latest_version' => $check->latest?->version,
            'latest_build' => $check->latest?->build_number,
            'mandatory' => $check->mandatory(),
        ];
    }

    private function connectivityStatus(
        TerminalP501 $terminal,
        CarbonInterface $now,
        ?CarbonInterface $lastSeenAt,
        ?CarbonInterface $lastActiveSeenAt,
        int $activeSessionsCount,
    ): string {
        if ($activeSessionsCount > 0 && $lastActiveSeenAt?->gte($now->copy()->subMinutes(self::ONLINE_MINUTES))) {
            return 'online';
        }

        if ($lastSeenAt?->gte($now->copy()->subHours(self::OFFLINE_HOURS))) {
            return 'monitor';
        }

        return 'offline';
    }

    private function carbonAttribute(TerminalP501 $terminal, string $attribute): ?CarbonInterface
    {
        $attributes = $terminal->getAttributes();
        $value = array_key_exists($attribute, $attributes)
            ? $attributes[$attribute]
            : $terminal->getRawOriginal($attribute);

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return $this->parseDatabaseTimestamp($value);
    }

    private function isLiveSession(TerminalSession $session): bool
    {
        $expiresAt = $this->sessionTimestamp($session, 'expires_at');
        $absoluteExpiresAt = $this->sessionTimestamp($session, 'absolute_expires_at');
        $now = now()->utc();

        return $expiresAt !== null
            && $expiresAt->isAfter($now)
            && ($absoluteExpiresAt === null || $absoluteExpiresAt->isAfter($now));
    }

    private function sessionTimestamp(TerminalSession $session, string $attribute): ?CarbonInterface
    {
        return $this->parseDatabaseTimestamp($session->getRawOriginal($attribute));
    }

    private function parseDatabaseTimestamp(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->utc();
        }

        return is_string($value) ? Carbon::parse($value, 'UTC') : null;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, TerminalAssignment>  $assignments
     * @param  Collection<int, User>  $actors
     * @return array<string, mixed>
     */
    private function serializeHistoryEvent(
        object $event,
        Collection $activities,
        Collection $assignments,
        Collection $actors,
    ): array {
        $eventData = get_object_vars($event);
        $source = (string) ($eventData['source'] ?? '');
        $sourceId = (int) ($eventData['source_id'] ?? 0);
        $actor = $this->actor((int) ($eventData['actor_id'] ?? 0), $actors);
        $metadata = [];
        $type = (string) ($eventData['event_type'] ?? '');
        $summary = $this->eventLabel($type);

        if ($source === 'activity') {
            $activity = $activities->get($sourceId);
            $properties = $activity?->properties;
            $metadata = $this->safeHistoryMetadata($properties?->toArray() ?? []);
            $summary = $this->eventLabel($type);
        } elseif ($source === 'assignment') {
            $assignment = $assignments->get($sourceId);

            if ($assignment) {
                $metadata = [
                    'assigned_user' => $assignment->assignedUser?->name,
                    'agency' => $assignment->agency?->name,
                    'reason' => $assignment->reason,
                    'unassigned_at' => $assignment->unassigned_at?->toISOString(),
                ];
                $summary = $assignment->unassigned_at ? 'Affectation clôturée' : 'Terminal affecté';
            }
        }

        return [
            'type' => $type,
            'occurred_at' => Carbon::parse((string) ($eventData['occurred_at'] ?? 'now'))->toISOString(),
            'actor' => $actor,
            'summary' => $summary,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<string, string|int|float|bool|null>
     */
    private function safeHistoryMetadata(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;

            if ($this->isSensitiveHistoryKey($key) || ! is_scalar($value) && $value !== null) {
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    private function isSensitiveHistoryKey(string $key): bool
    {
        $normalized = str($key)->lower()->toString();

        return in_array($normalized, ['id', 'ip', 'serial_number', 'session_uuid'], true)
            || str_ends_with($normalized, '_id')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'key');
    }

    private function historyDate(string $value, bool $endOfDay): ?CarbonInterface
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * @param  Collection<int, User>  $actors
     * @return array{uuid: string, name: string}|null
     */
    private function actor(int $actorId, Collection $actors): ?array
    {
        if ($actorId === 0) {
            return null;
        }

        $actor = $actors->get($actorId);

        return $actor ? ['uuid' => $actor->uuid, 'name' => $actor->name] : null;
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'terminal_registered' => 'Terminal enregistré',
            'terminal_assigned' => 'Terminal affecté',
            'terminal_unassigned' => 'Terminal désaffecté',
            'terminal_activated' => 'Terminal activé',
            'terminal_deactivated' => 'Terminal désactivé',
            'terminal_maintenance_set' => 'Terminal placé en maintenance',
            'terminal_lost' => 'Terminal déclaré perdu',
            'terminal_recovered' => 'Terminal récupéré',
            'terminal_returned_to_stock' => 'Terminal retourné au stock',
            'terminal_scan' => 'Scan enregistré',
            'terminal_print' => 'Impression enregistrée',
            'terminal_error' => 'Erreur remontée',
            'terminal_handshake' => 'Connexion terminal ouverte',
            'terminal_sync' => 'Synchronisation enregistrée',
            'ops_session_opened' => 'Session OPS ouverte',
            'ops_session_revoked' => 'Session OPS révoquée',
            default => str($event)->replace('_', ' ')->headline()->toString(),
        };
    }
}
