<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Statut du PASSAGER dans son parcours de réservation — distinct du statut
 * du BILLET (TicketStatus, propre au document émis). Un passager peut par
 * exemple être "embarqué" (ce statut) alors que son billet reste "issued"
 * (TicketStatus) : les deux informations ne se recouvrent pas et sont
 * affichées côte à côte, jamais fusionnées en un seul badge ambigu.
 *
 * Valeur toujours dérivée à la volée (jamais stockée) à partir de l'état
 * de la réservation/segment, du dernier paiement, et du statut du départ —
 * voir ResolvePassengerJourneyStatusAction.
 */
enum PassengerJourneyStatus: string
{
    use HasEnumUtilities;

    case HoldPending = 'hold_pending';
    case HoldExpired = 'hold_expired';
    case PaymentPending = 'payment_pending';
    case PaymentConfirmed = 'payment_confirmed';
    case TicketIssued = 'ticket_issued';
    case Boarded = 'boarded';
    case Absent = 'absent';
    case Cancelled = 'cancelled';
}
