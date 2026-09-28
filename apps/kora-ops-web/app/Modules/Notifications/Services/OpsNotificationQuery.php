<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Lecture des notifications d'un utilisateur : une ligne par destinataire,
 * décidée à la publication — jamais la notification d'un autre.
 */
class OpsNotificationQuery
{
    /**
     * Notifications reçues, hors celles déclenchées par l'utilisateur
     * lui-même — consultables via sentByUser().
     *
     * @return Builder<OpsNotification>
     */
    public function forUser(User $user): Builder
    {
        // Types dont l'utilisateur est l'acteur : inutile de l'en notifier.
        $selfTriggered = [
            OpsNotificationType::IncidentReported->value,
            OpsNotificationType::IncidentCritical->value,
            OpsNotificationType::DepartureDeparted->value,
        ];

        return OpsNotification::query()
            ->where('audience', 'ops')
            ->where('user_id', $user->id)
            ->whereDoesntHave(
                'event',
                fn (Builder $event) => $event
                    ->where('subject_user_id', $user->id)
                    ->whereIn('type', $selfTriggered)
            );
    }

    /**
     * Événements émis par l'utilisateur — une fois par événement,
     * quel que soit le nombre de destinataires notifiés.
     *
     * @return Builder<OpsNotificationEvent>
     */
    public function sentByUser(User $user): Builder
    {
        return OpsNotificationEvent::query()->where('subject_user_id', $user->id);
    }

    /**
     * @param  array{unread: bool, type: string|null, severity: string|null, since: Carbon|null}  $filters
     * @return LengthAwarePaginator<int, OpsNotification>
     */
    public function paginate(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->forUser($user)
            ->with(['event.departure.line', 'event.terminal'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['unread']) {
            $query->whereNull('read_at');
        }

        // `since` est inclusif : reprise après coupure, dédup par `uuid`.
        if ($filters['since'] !== null) {
            $query->where('created_at', '>=', $filters['since']);
        }

        if ($filters['type'] !== null || $filters['severity'] !== null) {
            $query
                ->when($filters['type'] !== null, fn (Builder $filtered) => $filtered->whereJsonContains('data', ['type' => $filters['type']]))
                ->when($filters['severity'] !== null, fn (Builder $filtered) => $filtered->whereJsonContains('data', ['severity' => $filters['severity']]));
        }

        return $query->paginate($perPage);
    }

    /**
     * Événements envoyés par l'utilisateur, les plus récents d'abord.
     *
     * @return LengthAwarePaginator<int, OpsNotificationEvent>
     */
    public function paginateSent(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->sentByUser($user)
            ->with(['departure.line', 'terminal'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{unread: int, critical_unread: int}
     */
    public function counts(User $user): array
    {
        $unread = $this->forUser($user)->whereNull('read_at');

        return [
            'unread' => (clone $unread)->count(),
            'critical_unread' => (clone $unread)
                ->where(function (Builder $query): void {
                    $query->whereHas('event', fn (Builder $event) => $event->where('severity', OpsNotificationSeverity::Critical->value))
                        ->orWhereJsonContains('data', ['severity' => OpsNotificationSeverity::Critical->value]);
                })
                ->count(),
        ];
    }
}
