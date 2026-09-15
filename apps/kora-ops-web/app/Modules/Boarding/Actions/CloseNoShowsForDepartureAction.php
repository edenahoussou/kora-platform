<?php

namespace App\Modules\Boarding\Actions;

use App\Models\User;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Clôture no-show d'un départ : chaque segment encore "ticketed" (jamais
 * embarqué) devient un no-show — libéré comme une descente anticipée
 * "dès le premier arrêt" (aucune distance parcourue), avec cascade sur
 * les jambes suivantes du même billet.
 *
 * Une seule opération HTTP peut couvrir des dizaines de billets, mais
 * seat_release_events exige un ticket_id par ligne : chaque billet no-show
 * obtient donc son propre SeatReleaseEvent, avec un operation_uuid dérivé
 * de façon déterministe (UUID5) de l'operation_uuid de clôture + le billet
 * — une relecture de la même clôture retombe exactement sur les mêmes
 * lignes, sans en recréer ni en oublier.
 */
class CloseNoShowsForDepartureAction
{
    /**
     * Espace de noms fixe pour la dérivation UUID5 — arbitraire mais
     * stable dans le temps (ne doit jamais changer).
     */
    private const string NAMESPACE = '1b2e6f2a-2f0a-4a1a-9c1a-6f1e4b2a8f1a';

    public function __construct(
        private ReleaseTicketSegmentsFromLegAction $releaseSegments,
    ) {}

    /**
     * @return Collection<int, SeatReleaseEvent>
     */
    public function execute(
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        string $closureOperationUuid,
        Carbon $localRecordedAt,
    ): Collection {
        $candidates = ReservationSegment::query()
            ->where('departure_id', $departure->id)
            ->where('status', ReservationSegmentStatus::Ticketed)
            ->get();

        return $candidates->map(function (ReservationSegment $segment) use ($departure, $terminal, $agent, $closureOperationUuid, $localRecordedAt) {
            $ticketOperationUuid = Uuid::uuid5(self::NAMESPACE, $closureOperationUuid.':'.$segment->reservation_passenger_id)->toString();

            $existing = SeatReleaseEvent::query()->where('operation_uuid', $ticketOperationUuid)->first();
            if ($existing !== null) {
                return $existing;
            }

            return DB::transaction(function () use ($segment, $departure, $terminal, $agent, $ticketOperationUuid, $localRecordedAt) {
                $raced = SeatReleaseEvent::query()->where('operation_uuid', $ticketOperationUuid)->lockForUpdate()->first();
                if ($raced !== null) {
                    return $raced;
                }

                // Course avec un autre agent/terminal ayant déjà traité ce
                // même segment entre la requête initiale et le verrou :
                // rien à faire, ce n'est pas une erreur.
                $locked = ReservationSegment::query()->whereKey($segment->getKey())->lockForUpdate()->first();
                if ($locked === null || $locked->status !== ReservationSegmentStatus::Ticketed) {
                    return null;
                }

                $ticket = Ticket::query()->where('reservation_passenger_id', $locked->reservation_passenger_id)->firstOrFail();

                $releasedSegments = $this->releaseSegments->execute($locked, $locked->originStop);

                return SeatReleaseEvent::query()->create([
                    'ticket_id' => $ticket->id,
                    'departure_id' => $departure->id,
                    'seat_id' => $locked->seat_id,
                    'actual_stop_id' => $locked->origin_stop_id,
                    'released_segments' => $releasedSegments->map(fn (ReservationSegment $s) => [
                        'segment_uuid' => $s->uuid,
                        'leg_order' => $s->leg_order,
                        'departure_uuid' => $s->departure?->uuid,
                    ])->values()->all(),
                    'terminal_p501_id' => $terminal->id,
                    'operation_uuid' => $ticketOperationUuid,
                    'local_recorded_at' => $localRecordedAt,
                    'server_recorded_at' => now(),
                    'created_by' => $agent->id,
                    'status' => SeatReleaseEventStatus::Accepted,
                    'conflict_payload' => ['reason' => 'no_show'],
                ]);
            });
        })->filter()->values();
    }
}
