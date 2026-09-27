<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Models\OpsNotification;
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
     * @return Builder<OpsNotification>
     */
    public function forUser(User $user): Builder
    {
        return OpsNotification::query()
            ->where('audience', 'ops')
            ->where('user_id', $user->id);
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
            $query
                ->when($filters['type'] !== null, fn (Builder $filtered) => $filtered->where('data->type', $filters['type']))
                ->when($filters['severity'] !== null, fn (Builder $filtered) => $filtered->where('data->severity', $filters['severity']));
        }

        return $query->paginate($perPage);
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
                        ->orWhere('data->severity', OpsNotificationSeverity::Critical->value);
                })
                ->count(),
        ];
    }
}
