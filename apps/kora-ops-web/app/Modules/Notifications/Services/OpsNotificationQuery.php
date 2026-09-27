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
 * Lecture des notifications d'un utilisateur. Le périmètre est porté par les
 * lignes elles-mêmes (un destinataire par ligne, décidé à la publication) :
 * un utilisateur ne lit — ni ne marque lue — jamais la notification d'un autre.
 */
class OpsNotificationQuery
{
    /**
     * Notifications REÇUES par l'utilisateur — exclut celles dont il est
     * lui-même à l'origine (`event.subject_user_id`) : se voir notifier de
     * son propre signalement d'incident ou de sa propre progression de
     * départ n'apporte rien (échange du 26/09/2026). Ces événements
     * restent consultables via [sentByUser].
     *
     * @return Builder<OpsNotification>
     */
    public function forUser(User $user): Builder
    {
        // Types dont l'utilisateur est l'ACTEUR (il a déclenché l'action) :
        // inutile de l'en notifier, il le sait déjà.
        $selfTriggered = [
            OpsNotificationType::IncidentReported->value,
            OpsNotificationType::IncidentCritical->value,
            OpsNotificationType::DepartureDeparted->value,
        ];

        return OpsNotification::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave(
                'event',
                fn (Builder $event) => $event
                    ->where('subject_user_id', $user->id)
                    ->whereIn('type', $selfTriggered)
            );
    }

    /**
     * Événements dont l'utilisateur est lui-même à l'origine (convoyeur
     * ayant signalé un incident, fait progresser un départ…) — pas une
     * notification par destinataire comme [forUser], un événement une
     * fois quel que soit le nombre de personnes notifiées.
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

        // `since` est inclusif : un client qui reprend après une coupure
        // repart de la date de la dernière notification reçue et déduplique
        // par `uuid` — mieux vaut une notification revue qu'une manquée.
        if ($filters['since'] !== null) {
            $query->where('created_at', '>=', $filters['since']);
        }

        if ($filters['type'] !== null || $filters['severity'] !== null) {
            $query->whereHas('event', function (Builder $event) use ($filters): void {
                if ($filters['type'] !== null) {
                    $event->where('type', $filters['type']);
                }

                if ($filters['severity'] !== null) {
                    $event->where('severity', $filters['severity']);
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Événements envoyés par l'utilisateur, les plus récents d'abord —
     * page « Notifications envoyées » (Profil), voir [sentByUser].
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
                ->whereHas('event', fn (Builder $event) => $event->where('severity', OpsNotificationSeverity::Critical->value))
                ->count(),
        ];
    }
}
