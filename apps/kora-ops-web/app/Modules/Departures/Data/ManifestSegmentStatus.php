<?php

namespace App\Modules\Departures\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Statut de PARCOURS exposé au convoyeur pour une ligne de manifeste —
 * volontairement plus restreint que PassengerJourneyStatus (interne, 8
 * valeurs) : un passager dont la réservation n'est pas encore
 * payée/confirmée (hold, paiement en attente...) n'apparaît pas du tout
 * sur le manifeste, voir ResolveManifestRowStatusAction.
 *
 * Distinct de TicketStatus (statut du BILLET — issued/used/cancelled/
 * refunded), retourné à côté sur chaque ligne : les deux informations ne
 * se recouvrent pas, même logique que PassengerJourneyStatus vs
 * TicketStatus côté OPS Web (voir ManifestController).
 */
enum ManifestSegmentStatus: string
{
    use HasEnumUtilities;

    case Paid = 'paid';
    case Boarded = 'boarded';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
