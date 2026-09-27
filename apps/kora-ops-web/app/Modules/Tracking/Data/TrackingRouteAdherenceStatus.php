<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Adhérence d'un bus à son itinéraire planifié, affichée sur fleet/tracking
 * (voir ComputeRouteAdherenceAction). Recalculée à la lecture, jamais
 * stockée : `unavailable` quand la ligne n'a pas assez d'arrêts
 * géolocalisés, `insufficient_data` quand aucune position n'est connue.
 */
enum TrackingRouteAdherenceStatus: string
{
    use HasEnumUtilities;

    case OnRoute = 'on_route';
    case Deviation = 'deviation';
    case Unavailable = 'unavailable';
    case InsufficientData = 'insufficient_data';
}
