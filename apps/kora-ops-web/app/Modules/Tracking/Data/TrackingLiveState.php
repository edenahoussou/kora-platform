<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * État affiché côté OPS Web pour une session de suivi — jamais stocké,
 * toujours recalculé à la lecture (voir FleetTrackingStateResolver). Ordre
 * de résolution : une session close prime sur tout, puis un terminal jugé
 * hors ligne, puis l'absence de GPS signalée par le terminal lui-même,
 * puis l'âge de la dernière position.
 */
enum TrackingLiveState: string
{
    use HasEnumUtilities;

    case Live = 'live';
    case Stale = 'stale';
    case GpsUnavailable = 'gps_unavailable';
    case TerminalOffline = 'terminal_offline';
    case SessionClosed = 'session_closed';
}
