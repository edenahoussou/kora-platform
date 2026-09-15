<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Segments d'un départ retenus pour le manifeste — exclut les segments
 * pas encore payés/confirmés (hold, paiement en attente...), qui n'ont
 * pas de billet à présenter à l'embarquement. Partagé entre
 * OpsDepartureController (manifeste complet) et la vérification de
 * version en masse côté sync (CheckManifestVersionsAction) : les deux
 * doivent calculer exactement le même ensemble de segments pour que le
 * hash de version soit cohérent.
 */
class ResolveManifestSegmentsAction
{
    public function __construct(
        private ResolveManifestRowStatusAction $resolveStatus,
    ) {}

    /**
     * @return array{Collection<int, ReservationSegment>, array<int, Ticket>, array<int, ManifestSegmentStatus>}
     */
    public function execute(Departure $departure): array
    {
        $segments = $departure->reservationSegments()
            ->with([
                'reservationPassenger',
                'reservation.payments' => fn ($q) => $q->orderByDesc('created_at'),
                'seat:id,uuid,code',
                'originStop:id,uuid,name',
                'destinationStop:id,uuid,name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $passengerIds = $segments->pluck('reservation_passenger_id')->filter()->unique()->values();
        $ticketsByPassengerId = $passengerIds->isEmpty() ? [] : Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->get()
            ->keyBy('reservation_passenger_id')
            ->all();

        $statusesBySegmentId = [];
        $included = $segments->filter(function ($segment) use (&$statusesBySegmentId, $departure) {
            $latestPayment = $segment->reservation?->payments->first();
            $status = $this->resolveStatus->execute($segment, $latestPayment, $departure->status);

            if ($status === null) {
                return false;
            }

            $statusesBySegmentId[$segment->id] = $status;

            return true;
        })->values();

        return [$included, $ticketsByPassengerId, $statusesBySegmentId];
    }
}
