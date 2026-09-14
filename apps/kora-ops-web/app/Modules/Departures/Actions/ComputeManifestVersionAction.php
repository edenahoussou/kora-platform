<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Collection;

class ComputeManifestVersionAction
{
    /**
     * Hash court et stable détectant toute mise à jour du manifeste — pas
     * de colonne dédiée : recalculé à la volée à partir de ce qui peut
     * réellement changer un manifeste déjà téléchargé (statut du départ,
     * et pour chaque segment retenu : son statut et celui de son billet).
     * Le mobile compare ce hash à celui qu'il a en cache pour savoir s'il
     * doit retélécharger.
     *
     * @param  Collection<int, ReservationSegment>  $segments  Segments déjà filtrés (ceux effectivement inclus dans le manifeste).
     * @param  array<int, Ticket>  $ticketsByPassengerId
     */
    public function execute(Departure $departure, Collection $segments, array $ticketsByPassengerId): string
    {
        $parts = [$departure->id, $departure->status->value];

        foreach ($segments->sortBy('id') as $segment) {
            $ticket = $segment->reservation_passenger_id !== null
                ? ($ticketsByPassengerId[$segment->reservation_passenger_id] ?? null)
                : null;

            // CORRECTION : Remplacement de ?->id ?? par ternaire explicite
            $ticketId = $ticket !== null ? $ticket->id : '-';
            $ticketStatus = $ticket !== null ? $ticket->status->value : '-';

            $parts[] = implode(':', [
                $segment->id,
                $segment->status->value,
                $ticketId,
                $ticketStatus,
            ]);
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }
}
