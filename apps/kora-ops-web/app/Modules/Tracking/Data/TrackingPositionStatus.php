<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * `Fix` : coordonnées réelles. `GpsUnavailable` : heartbeat du terminal
 * signalant explicitement un GPS désactivé ou une perte de signal — pas de
 * coordonnées, mais confirme que le terminal est toujours en ligne (par
 * opposition à une simple absence de lot, qui finit par déclencher
 * `terminal_offline`).
 */
enum TrackingPositionStatus: string
{
    use HasEnumUtilities;

    case Fix = 'fix';
    case GpsUnavailable = 'gps_unavailable';
}
