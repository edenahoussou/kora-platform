<?php

namespace App\Modules\Boarding\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Collection;

/**
 * Libère un segment (descente anticipée ou no-show) et toutes les jambes
 * suivantes du même billet, jamais parcourues — la disponibilité par
 * segment se recalcule ensuite d'elle-même : ReservationSegmentStatus::
 * Released n'est pas dans occupyingValues(), donc les requêtes de vente
 * (FindOccupiedSeatIdsAction) l'ignorent déjà sans code supplémentaire.
 */
class ReleaseTicketSegmentsFromLegAction
{
    /**
     * @return Collection<int, ReservationSegment> Segments effectivement libérés (celui-ci + les suivants).
     */
    public function execute(ReservationSegment $fromSegment, Stop $actualStop): Collection
    {
        $released = collect();

        $fromSegment->update([
            'status' => ReservationSegmentStatus::Released,
            'actual_dropoff_stop_id' => $actualStop->id,
            'released_at' => now(),
        ]);
        $released->push($fromSegment);

        $subsequentLegs = ReservationSegment::query()
            ->where('reservation_passenger_id', $fromSegment->reservation_passenger_id)
            ->where('leg_order', '>', $fromSegment->leg_order)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->lockForUpdate()
            ->get();

        foreach ($subsequentLegs as $leg) {
            // Jamais embarquée : le point de "descente" est son propre
            // arrêt d'origine (aucune distance parcourue sur cette jambe).
            $leg->update([
                'status' => ReservationSegmentStatus::Released,
                'actual_dropoff_stop_id' => $leg->origin_stop_id,
                'released_at' => now(),
            ]);
            $released->push($leg);
        }

        return $released;
    }
}
