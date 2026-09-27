<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Data\TrackingLiveState;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use App\Modules\Tracking\Models\TrackingSession;
use Illuminate\Support\Carbon;

/**
 * État affiché d'une session de suivi (ticket MEZ-100 : "signaler
 * explicitement les états live, position ancienne, GPS indisponible,
 * terminal hors ligne et session clôturée"). Jamais stocké : recalculé à
 * chaque lecture à partir de la session et de la fraîcheur du terminal.
 *
 * Ordre de résolution (le premier qui s'applique gagne) :
 *  1. session close → `session_closed` (l'historique reste consultable,
 *     mais n'est plus « en circulation »)
 *  2. terminal sans activité récente (aucune requête ops.session depuis
 *     `trackingTerminalOfflineAfterSeconds`) → `terminal_offline` — prime sur
 *     l'âge de la position : un terminal injoignable n'est pas juste "en
 *     retard", il ne répond plus du tout
 *  3. dernier signal du terminal = heartbeat GPS indisponible → `gps_unavailable`
 *  4. dernière position plus vieille que `trackingStaleAfterSeconds` → `stale`
 *  5. sinon → `live`
 */
class FleetTrackingStateResolver
{
    public function resolve(TrackingSession $session, ?Carbon $terminalLastSeenAt): TrackingLiveState
    {
        if (! $session->isOpen()) {
            return TrackingLiveState::SessionClosed;
        }

        $offlineAfter = app(OperationsSettings::class)->trackingTerminalOfflineAfterSeconds;

        if ($terminalLastSeenAt === null || $terminalLastSeenAt->lte(now()->subSeconds($offlineAfter))) {
            return TrackingLiveState::TerminalOffline;
        }

        if ($session->last_status === TrackingPositionStatus::GpsUnavailable) {
            return TrackingLiveState::GpsUnavailable;
        }

        $staleAfter = app(OperationsSettings::class)->trackingStaleAfterSeconds;

        if ($session->last_position_at === null || $session->last_position_at->lte(now()->subSeconds($staleAfter))) {
            return TrackingLiveState::Stale;
        }

        return TrackingLiveState::Live;
    }

    public function ageSeconds(TrackingSession $session): ?int
    {
        return $session->last_position_at === null
            ? null
            : (int) $session->last_position_at->diffInSeconds(now());
    }
}
