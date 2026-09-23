<?php

namespace App\Modules\Tracking\Actions;

use App\Modules\Tracking\Data\TrackingSessionCloseReason;
use App\Modules\Tracking\Data\TrackingSessionStatus;
use App\Modules\Tracking\Models\TrackingSession;
use App\Modules\Tracking\Services\TrackingPositionCache;

/**
 * Clôture une session de suivi (arrivée, annulation, terminal perdu,
 * réaffecté, ou clôture manuelle). Idempotent : une session déjà close
 * n'est pas modifiée.
 */
class CloseTrackingSessionAction
{
    public function __construct(private TrackingPositionCache $cache) {}

    public function execute(TrackingSession $session, TrackingSessionCloseReason $reason): TrackingSession
    {
        if (! $session->isOpen()) {
            return $session;
        }

        $session->forceFill([
            'status' => TrackingSessionStatus::Closed,
            'closed_at' => now(),
            'close_reason' => $reason,
        ])->save();

        $this->cache->put($session);

        return $session;
    }
}
