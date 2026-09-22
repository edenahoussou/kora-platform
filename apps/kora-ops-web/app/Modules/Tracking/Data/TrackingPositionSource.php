<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Source de la localisation transmise par le terminal (P501 ou téléphone
 * Android — voir docs/tracking-location-source.md). `gps` est la source
 * attendue en usage normal ; `network`/`fused` sont acceptées comme
 * dégradation gérée par l'OS quand le signal satellite est instable
 * (tunnel, zone urbaine dense) plutôt que rejetées.
 */
enum TrackingPositionSource: string
{
    use HasEnumUtilities;

    case Gps = 'gps';
    case Network = 'network';
    case Fused = 'fused';
    case Unknown = 'unknown';
}
