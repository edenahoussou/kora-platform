<?php

namespace App\Modules\Boarding\Actions;

use App\Models\User;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Data\SeatReleaseFunctionalCode;
use App\Modules\Boarding\Exceptions\SeatReleaseTicketUnknownException;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Signale un no-show individuel (un billet précis, contrairement à
 * CloseNoShowsForDepartureAction qui clôture tout un départ) : libère le
 * segment jamais embarqué de ce billet sur ce départ, et toutes les
 * jambes suivantes du même billet. Même mécanisme que la descente
 * anticipée (SeatReleaseEvent), même distinction conflit/rejet entre
 * terminaux — seule la condition de départ change (Ticketed, pas
 * Boarded), et le départ doit déjà être parti ou arrivé.
 */
class ProcessIndividualNoShowAction
{
    public function __construct(
        private ReleaseTicketSegmentsFromLegAction $releaseSegments,
    ) {}

    /**
     * @throws SeatReleaseTicketUnknownException Seul cas non persisté (pas de ticket_id valide).
     */
    public function execute(
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        string $ticketUuid,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
    ): SeatReleaseEvent {
        $existing = SeatReleaseEvent::query()->where('operation_uuid', $operationUuid)->first();
        if ($existing !== null) {
            return $existing;
        }

        $ticket = Ticket::query()->where('uuid', $ticketUuid)->first();
        if ($ticket === null) {
            throw new SeatReleaseTicketUnknownException;
        }

        return DB::transaction(function () use ($departure, $terminal, $agent, $ticket, $operationUuid, $localRecordedAt) {
            $raced = SeatReleaseEvent::query()->where('operation_uuid', $operationUuid)->lockForUpdate()->first();
            if ($raced !== null) {
                return $raced;
            }

            $fallbackSeatId = $ticket->reservationPassenger?->segments()->orderBy('leg_order')->value('seat_id');
            $fallbackStopId = $departure->line->stops()->orderBy('stop_order')->value('id');

            if (! in_array($departure->status, [DepartureStatus::Departed, DepartureStatus::Arrived], true)) {
                return $this->reject($ticket, $departure, $terminal, $agent, $fallbackSeatId, $fallbackStopId, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::DepartureNotYetClosable);
            }

            if (in_array($ticket->status, [TicketStatus::Cancelled, TicketStatus::Refunded], true)) {
                return $this->reject($ticket, $departure, $terminal, $agent, $fallbackSeatId, $fallbackStopId, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::TicketCancelled);
            }

            $segment = ReservationSegment::query()
                ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
                ->where('departure_id', $departure->id)
                ->lockForUpdate()
                ->first();

            if ($segment === null) {
                return $this->reject($ticket, $departure, $terminal, $agent, $fallbackSeatId, $fallbackStopId, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::WrongDeparture);
            }

            if ($segment->status === ReservationSegmentStatus::Released) {
                return $this->handleAlreadyReleased($ticket, $departure, $terminal, $agent, $segment, $operationUuid, $localRecordedAt);
            }

            if ($segment->status !== ReservationSegmentStatus::Ticketed) {
                // Boarded (le passager s'est bien présenté — ce n'est pas
                // un no-show), Cancelled, Expired... aucun n'est éligible.
                return $this->reject($ticket, $departure, $terminal, $agent, $segment->seat_id, $segment->origin_stop_id, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::SegmentNotEligibleForNoShow);
            }

            $releasedSegments = $this->releaseSegments->execute($segment, $segment->originStop);

            return SeatReleaseEvent::query()->create([
                'ticket_id' => $ticket->id,
                'departure_id' => $departure->id,
                'seat_id' => $segment->seat_id,
                'actual_stop_id' => $segment->origin_stop_id,
                'released_segments' => $releasedSegments->map(fn (ReservationSegment $s) => [
                    'segment_uuid' => $s->uuid,
                    'leg_order' => $s->leg_order,
                    'departure_uuid' => $s->departure?->uuid,
                ])->values()->all(),
                'terminal_p501_id' => $terminal->id,
                'operation_uuid' => $operationUuid,
                'local_recorded_at' => $localRecordedAt,
                'server_recorded_at' => now(),
                'created_by' => $agent->id,
                'status' => SeatReleaseEventStatus::Accepted,
                'conflict_payload' => ['reason' => 'no_show'],
            ]);
        });
    }

    /**
     * Même distinction que ProcessSeatReleaseAction : même terminal =
     * doublon rejeté, terminal différent = conflit déterministe (verrou
     * transactionnel).
     */
    private function handleAlreadyReleased(
        Ticket $ticket,
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        ReservationSegment $segment,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
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
                'actual_stop_id' => $segment->origin_stop_id,
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

        return $this->reject($ticket, $departure, $terminal, $agent, $segment->seat_id, $segment->origin_stop_id, $operationUuid, $localRecordedAt, SeatReleaseFunctionalCode::SegmentAlreadyReleased);
    }

    private function reject(
        Ticket $ticket,
        Departure $departure,
        TerminalP501 $terminal,
        User $agent,
        ?int $seatId,
        ?int $actualStopId,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
        SeatReleaseFunctionalCode $code,
    ): SeatReleaseEvent {
        return SeatReleaseEvent::query()->create([
            'ticket_id' => $ticket->id,
            'departure_id' => $departure->id,
            'seat_id' => $seatId,
            'actual_stop_id' => $actualStopId,
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
