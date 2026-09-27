<?php

namespace App\Modules\Tracking\Services;

use App\Models\User;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Http\Resources\FleetTrackingResource;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Départs actifs visibles sur fleet/tracking (ticket MEZ-100) : deux
 * permissions distinctes, sur le même modèle que ExpenseQuery/`tickets.view-network`
 * — `tracking.view-agency` (gestionnaire d'agence : uniquement les sessions
 * dont le terminal appartient à SON agence) et `tracking.view-network`
 * (Directeur des opérations, Directeur commercial, Direction générale, Admin
 * système : toute la flotte, avec un filtre optionnel par agence).
 */
class FleetTrackingQuery
{
    /**
     * @param  array{agency?: string}  $filters
     * @return Builder<TrackingSession>
     */
    public function build(User $user, array $filters = []): Builder
    {
        $query = TrackingSession::query()
            ->open()
            ->with([
                'departure.line.stops' => fn ($stops) => $stops->where('status', StopStatus::Active->value)->orderBy('stop_order'),
                'departure.conductor',
                'departure.driver',
                'departure.secondaryDriver',
                'departure.mechanic',
                'departure.incidents.creator',
                'bus.busModel',
                'terminal.agency',
                'conductor',
                'lastPosition',
            ]);

        if ($user->can('tracking.view-network')) {
            $agencyUuid = $filters['agency'] ?? null;

            return $query->when(
                $agencyUuid !== null && $agencyUuid !== '',
                fn (Builder $query) => $query->whereHas('terminal', fn (Builder $terminal) => $terminal->whereHas('agency', fn (Builder $agency) => $agency->where('uuid', $agencyUuid))),
            );
        }

        if (! $user->can('tracking.view-agency') || $user->agency_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('terminal', fn (Builder $terminal) => $terminal->where('agency_id', $user->agency_id));
    }

    /**
     * Dernière activité connue de chaque terminal (n'importe quelle requête
     * gardée par ops.session, pas seulement l'ingestion de position) —
     * utilisée pour détecter un terminal hors ligne. Une requête groupée
     * plutôt qu'une par session : fleet/tracking est lu très fréquemment.
     *
     * @param  Collection<int, TrackingSession>  $sessions
     * @return array<int, Carbon|null> terminal_p501_id => last_seen_at
     */
    public function terminalLastSeenAt(Collection $sessions): array
    {
        $terminalIds = $sessions->pluck('terminal_p501_id')->unique()->values();

        if ($terminalIds->isEmpty()) {
            return [];
        }

        return TerminalSession::query()
            ->whereIn('terminal_p501_id', $terminalIds)
            ->active()
            ->selectRaw('terminal_p501_id, MAX(last_seen_at) as last_seen_at')
            ->groupBy('terminal_p501_id')
            ->pluck('last_seen_at', 'terminal_p501_id')
            ->map(fn (?string $value): ?Carbon => $value === null ? null : Carbon::parse($value))
            ->all();
    }

    /**
     * Positions récentes géolocalisées de chaque session, pour la
     * trajectoire dessinée sur la carte. Une seule requête groupée (la
     * lecture est très fréquente) ; chaque session est bornée à
     * `trackingRecentPositionsLimit` points, les plus récents d'abord.
     *
     * @param  Collection<int, TrackingSession>  $sessions
     * @return array<int, Collection<int, TrackingPosition>> tracking_session_id => positions
     */
    public function recentPositionsBySession(Collection $sessions): array
    {
        $sessionIds = $sessions->pluck('id');

        if ($sessionIds->isEmpty()) {
            return [];
        }

        $limit = app(OperationsSettings::class)->trackingRecentPositionsLimit;

        return TrackingPosition::query()
            ->whereIn('tracking_session_id', $sessionIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('tracking_session_id')
            ->orderByDesc('sequence')
            ->get()
            ->groupBy('tracking_session_id')
            ->map(fn (Collection $positions): Collection => $positions->take($limit)->values())
            ->all();
    }

    /**
     * Sessions résolues en contrat fleet/tracking (FleetTrackingResource) —
     * point unique utilisé par la page Inertia, l'API de polling et le
     * broadcast temps réel, pour que les trois voient exactement la même
     * charge utile.
     *
     * @param  Collection<int, TrackingSession>  $sessions
     * @return Collection<int, array<string, mixed>>
     */
    public function present(Collection $sessions): Collection
    {
        $lastSeenAt = $this->terminalLastSeenAt($sessions);
        $recentPositions = $this->recentPositionsBySession($sessions);

        return $sessions
            ->map(function (TrackingSession $session) use ($lastSeenAt, $recentPositions): array {
                /** @var array<string, mixed> $payload */
                $payload = (new FleetTrackingResource(
                    $session,
                    $lastSeenAt[$session->terminal_p501_id] ?? null,
                    $recentPositions[$session->id] ?? collect(),
                ))->resolve(request());

                return $payload;
            })
            ->values();
    }

    /**
     * Le périmètre effectif de l'utilisateur, pour le canal de diffusion
     * temps réel (voir FleetPositionUpdated) : `network` pour un rôle
     * réseau, l'UUID de l'agence sinon.
     */
    public function scopeFor(User $user): ?string
    {
        if ($user->can('tracking.view-network')) {
            return 'network';
        }

        return $user->agency?->uuid;
    }
}
