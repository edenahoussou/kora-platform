<?php

namespace App\Modules\Boarding\Actions;

use App\Models\User;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Data\SeatReleaseFunctionalCode;
use App\Modules\Boarding\Exceptions\SeatReleaseTicketUnknownException;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une descente anticipée : libère la portion non parcourue du
 * segment courant (celui embarqué) et toutes les jambes suivantes du même
 * billet, jamais parcourues. La disponibilité par segment se recalcule
 * ensuite d'elle-même : ReservationSegmentStatus::Released n'est pas dans
 * occupyingValues(), donc les requêtes de vente l'ignorent déjà sans code
 * supplémentaire (voir FindOccupiedSeatIdsAction).
 */
class ProcessSeatReleaseAction
{
    public function __construct(
        private ReleaseTicketSegmentsFromLegAction $releaseSegments,
        private DepartureStopTimeResolver $stopTimeResolver,
    ) {}

    /**
     * @throws SeatReleaseTicketUnknownException Seul cas non persisté (pas de ticket_id valide).
     */
    public function execute(
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        string $ticketUuid,
        Stop $actualStop,
        string $operationUuid,
        Carbon $localRecordedAt,
    ): SeatReleaseEvent {
        $existing = SeatReleaseEvent::query()->where('operation_uuid', $operationUuid)->first();
        if ($existing !== null) {
            return $existing;
        }

        $ticket = Ticket::query()->where('uuid', $ticketUuid)->first();
        if ($ticket === null) {
            throw new SeatReleaseTicketUnknownException;
        }

        return DB::transaction(function () use ($departure, $terminal, $agent, $ticket, $actualStop, $operationUuid, $localRecordedAt) {
            $raced = SeatReleaseEvent::query()->where('operation_uuid', $operationUuid)->lockForUpdate()->first();
            if ($raced !== null) {
                return $raced;
            }

            // Repli pour seat_id (colonne NOT NULL) quand aucun segment
            // pour CE départ n'existe encore : n'importe quel segment du
            // billet identifie déjà le siège assigné au passager.
            $fallbackSeatId = $ticket->reservationPassenger?->segments()->orderBy('leg_order')->value('seat_id');

            if (in_array($ticket->status, [TicketStatus::Cancelled, TicketStatus::Refunded], true)) {
                return $this->reject($ticket, $departure, $terminal, $agent, $fallbackSeatId, $actualStop, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::TicketCancelled);
            }

            $segment = ReservationSegment::query()
                ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
                ->where('departure_id', $departure->id)
                ->lockForUpdate()
                ->first();

            if ($segment === null) {
                return $this->reject($ticket, $departure, $terminal, $agent, $fallbackSeatId, $actualStop, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::WrongDeparture);
            }

            if ($segment->status === ReservationSegmentStatus::Released) {
                return $this->handleAlreadyReleased($ticket, $departure, $terminal, $agent, $segment, $actualStop, $operationUuid, $localRecordedAt);
            }

            if ($segment->status !== ReservationSegmentStatus::Boarded) {
                return $this->reject($ticket, $departure, $terminal, $agent, $segment->seat_id, $actualStop, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::SegmentNotBoarded);
            }

            if (! $this->isStopCompatible($departure, $segment, $actualStop)) {
                return $this->reject($ticket, $departure, $terminal, $agent, $segment->seat_id, $actualStop, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::StopNotCompatible);
            }

            $releasedSegments = $this->releaseSegments->execute($segment, $actualStop);

            return SeatReleaseEvent::query()->create([
                'ticket_id' => $ticket->id,
                'departure_id' => $departure->id,
                'seat_id' => $segment->seat_id,
                'actual_stop_id' => $actualStop->id,
                'released_segments' => $this->describeSegments($releasedSegments),
                'terminal_p501_id' => $terminal->id,
                'operation_uuid' => $operationUuid,
                'local_recorded_at' => $localRecordedAt,
                'server_recorded_at' => now(),
                'created_by' => $agent->id,
                'status' => SeatReleaseEventStatus::Accepted,
            ]);
        });
    }

    /**
     * L'arrêt réel doit être compatible avec la jambe en cours et la
     * destination initiale : même ligne que le départ, et sa position
     * dans la circulation du départ (qui peut être inversée pour un
     * retour — voir DepartureStopTimeResolver) doit se situer entre
     * l'origine et la destination du segment, strictement avant la
     * destination (sinon ce n'est pas une descente "anticipée", juste la
     * fin normale du trajet).
     */
    private function isStopCompatible(Departure $departure, ReservationSegment $segment, Stop $actualStop): bool
    {
        if ($actualStop->line_id !== $departure->line_id) {
            return false;
        }

        $actualIndex = $this->stopTimeResolver->circulationIndexOf($departure, $actualStop);

        return $actualIndex >= $segment->origin_stop_order && $actualIndex < $segment->destination_stop_order;
    }

    /**
     * Segment déjà libéré : même distinction que pour l'embarquement
     * (MEZ-84) — un second rapport depuis le MÊME terminal est un simple
     * doublon (rejeté), depuis un terminal DIFFÉRENT c'est un conflit
     * (deux terminaux ayant chacun accepté la libération hors ligne,
     * résolu de façon déterministe par le verrou transactionnel : le
     * premier à committer devient la libération d'origine, quel que soit
     * l'ordre d'arrivée réseau).
     */
    private function handleAlreadyReleased(
        Ticket $ticket,
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        ReservationSegment $segment,
        Stop $actualStop,
        string $operationUuid,
        Carbon $localRecordedAt,
    ): SeatReleaseEvent {
        $originalEvent = SeatReleaseEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', SeatReleaseEventStatus::Accepted)
            ->latest('server_recorded_at')
            ->first();

        $isCrossTerminalConflict = $originalEvent !== null
            && $originalEvent->terminal_p501_id !== null
            && $originalEvent->terminal_p501_id !== $terminal->id;

        if ($isCrossTerminalConflict) {
            return SeatReleaseEvent::query()->create([
                'ticket_id' => $ticket->id,
                'departure_id' => $departure->id,
                'seat_id' => $segment->seat_id,
                'actual_stop_id' => $actualStop->id,
                'released_segments' => [],
                'terminal_p501_id' => $terminal->id,
                'operation_uuid' => $operationUuid,
                'local_recorded_at' => $localRecordedAt,
                'server_recorded_at' => now(),
                'created_by' => $agent->id,
                'status' => SeatReleaseEventStatus::Conflict,
                'conflict_payload' => [
                    'code' => SeatReleaseFunctionalCode::ReleaseConflict->value,
                    'message' => SeatReleaseFunctionalCode::ReleaseConflict->message(),
                    'recommendedAction' => SeatReleaseFunctionalCode::ReleaseConflict->recommendedAction(),
                    'accepted_event_uuid' => $originalEvent->uuid,
                    'accepted_terminal_uuid' => $originalEvent->terminal?->uuid,
                    'accepted_local_recorded_at' => $originalEvent->local_recorded_at->toISOString(),
                    'conflicting_terminal_uuid' => $terminal->uuid,
                    'conflicting_local_recorded_at' => $localRecordedAt->toISOString(),
                ],
            ]);
        }

        return $this->reject($ticket, $departure, $terminal, $agent, $segment->seat_id, $actualStop, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::SegmentAlreadyReleased);
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return list<array<string, mixed>>
     */
    private function describeSegments(Collection $segments): array
    {
        return array_values($segments->map(fn (ReservationSegment $s) => [
            'segment_uuid' => $s->uuid,
            'leg_order' => $s->leg_order,
            'departure_uuid' => $s->departure?->uuid,
        ])->all());
    }

    private function reject(
        Ticket $ticket,
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        ?int $seatId,
        Stop $actualStop,
        string $operationUuid,
        Carbon $localRecordedAt,
        SeatReleaseFunctionalCode $code,
    ): SeatReleaseEvent {
        return SeatReleaseEvent::query()->create([
            'ticket_id' => $ticket->id,
            'departure_id' => $departure->id,
            'seat_id' => $seatId,
            'actual_stop_id' => $actualStop->id,
            'released_segments' => [],
            'terminal_p501_id' => $terminal->id,
            'operation_uuid' => $operationUuid,
            'local_recorded_at' => $localRecordedAt,
            'server_recorded_at' => now(),
            'created_by' => $agent->id,
            'status' => SeatReleaseEventStatus::Rejected,
            'conflict_payload' => [
                'code' => $code->value,
                'message' => $code->message(),
                'recommendedAction' => $code->recommendedAction(),
            ],
        ]);
    }
}
