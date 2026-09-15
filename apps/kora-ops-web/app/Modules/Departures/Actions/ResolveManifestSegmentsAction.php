<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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
        $noShowSegmentUuids = $this->resolveNoShowSegmentUuids($segments, $ticketsByPassengerId);

        $included = $segments->filter(function (ReservationSegment $segment) use (&$statusesBySegmentId, $departure, $noShowSegmentUuids) {
            $latestPayment = $segment->reservation?->payments->first();
            $wasReleasedAsNoShow = $noShowSegmentUuids->contains($segment->uuid);
            $status = $this->resolveStatus->execute($segment, $latestPayment, $departure->status, $wasReleasedAsNoShow);

            if ($status === null) {
                return false;
            }

            $statusesBySegmentId[$segment->id] = $status;

            return true;
        })->values();

        return [$included, $ticketsByPassengerId, $statusesBySegmentId];
    }

    /**
     * Un segment "released" peut venir d'une descente anticipée (le
     * passager a bien voyagé, juste pas jusqu'au bout) ou d'un no-show
     * (jamais monté) — ReservationSegmentStatus les traite tous deux
     * comme "cancelled" côté journey status, ce qui masquerait le
     * no-show sur le manifeste. On retrouve le motif via
     * seat_release_events.conflict_payload (rempli 'no_show' par
     * ProcessIndividualNoShowAction / CloseNoShowsForDepartureAction),
     * sans requête JSON spécifique au moteur (portable SQLite/Postgres) :
     * un seul whereIn par ticket, puis filtrage en PHP sur
     * released_segments.
     *
     * @param  Collection<int, ReservationSegment>  $segments
     * @param  array<int, Ticket>  $ticketsByPassengerId
     * @return SupportCollection<int, string>
     */
    private function resolveNoShowSegmentUuids(Collection $segments, array $ticketsByPassengerId): SupportCollection
    {
        $releasedSegments = $segments->filter(
            fn (ReservationSegment $s) => $s->status === ReservationSegmentStatus::Released,
        );

        if ($releasedSegments->isEmpty()) {
            return collect();
        }

        // ✅ CORRECTION : Utilisation d'une closure explicite pour éviter l'erreur nullCoalesce.expr de PHPStan
        $ticketIds = $releasedSegments
            ->map(function (ReservationSegment $s) use ($ticketsByPassengerId): ?int {
                $ticket = $ticketsByPassengerId[$s->reservation_passenger_id] ?? null;

                return $ticket !== null ? $ticket->id : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($ticketIds->isEmpty()) {
            return collect();
        }

        return SeatReleaseEvent::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('status', SeatReleaseEventStatus::Accepted)
            ->get(['released_segments', 'conflict_payload'])
            ->filter(fn (SeatReleaseEvent $event) => ($event->conflict_payload['reason'] ?? null) === 'no_show')
            ->flatMap(fn (SeatReleaseEvent $event) => collect($event->released_segments)->pluck('segment_uuid'))
            ->unique()
            ->values();
    }
}
