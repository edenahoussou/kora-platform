<?php

namespace App\Modules\Boarding\Actions;

use App\Modules\Boarding\Data\BoardingEventStatus;
use App\Modules\Boarding\Data\BoardingFunctionalCode;
use App\Modules\Boarding\Exceptions\BoardingTicketUnknownException;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProcessBoardingScanAction
{
    public function __construct(
        private ResolveTicketForBoardingAction $resolveTicket,
        private OpsNotifier $notifier,
    ) {}

    /**
     * Traite un scan d'embarquement de bout en bout : idempotence,
     * résolution du billet, validation de la jambe active, et — si tout
     * est bon — passage du segment à "boarded". Toute tentative dont le
     * billet a pu être résolu est journalisée (acceptée, rejetée ou en
     * conflit) ; seul le cas "billet inconnu" (signature invalide, uuid
     * inconnu) ne l'est pas, faute de ticket_id valide à rattacher.
     *
     * @throws BoardingTicketUnknownException
     */
    public function execute(
        Departure $departure,
        TerminalP501 $terminal,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
        ?string $qrPayload,
        ?string $shortCode,
    ): BoardingEvent {
        $existing = BoardingEvent::query()->where('operation_uuid', $operationUuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        $ticket = $this->resolveTicket->execute($qrPayload, $shortCode);

        $event = DB::transaction(function () use ($departure, $terminal, $operationUuid, $localRecordedAt, $ticket) {
            // Idempotence sous course concurrente : la contrainte unique
            // sur operation_uuid peut avoir été posée par une requête
            // concurrente entre notre lecture initiale et ce point.
            $raced = BoardingEvent::query()->where('operation_uuid', $operationUuid)->lockForUpdate()->first();
            if ($raced !== null) {
                return $raced;
            }

            if (in_array($ticket->status, [TicketStatus::Cancelled, TicketStatus::Refunded], true)) {
                return $this->reject($ticket, $departure, $terminal, $operationUuid, $localRecordedAt, BoardingFunctionalCode::TicketCancelled);
            }

            $segment = ReservationSegment::query()
                ->where('reservation_passenger_id', $ticket->reservation_passenger_id)
                ->where('departure_id', $departure->id)
                ->lockForUpdate()
                ->first();

            if ($segment === null) {
                return $this->reject($ticket, $departure, $terminal, $operationUuid, $localRecordedAt, BoardingFunctionalCode::WrongDeparture);
            }

            // Éligibilité du passager : vérifiée indépendamment du statut
            // du segment/billet (qui devraient déjà refléter une
            // annulation passager via CancelReservationAction, mais on ne
            // veut pas dépendre silencieusement de cette cascade pour
            // toujours — défense en profondeur explicite).
            if ($segment->reservationPassenger?->status !== 'active') {
                return $this->reject($ticket, $departure, $terminal, $operationUuid, $localRecordedAt, BoardingFunctionalCode::LegNotActive);
            }

            if ($segment->status === ReservationSegmentStatus::Boarded) {
                return $this->handleAlreadyBoarded($ticket, $segment, $departure, $terminal, $operationUuid, $localRecordedAt);
            }

            if ($segment->status !== ReservationSegmentStatus::Ticketed) {
                return $this->reject($ticket, $departure, $terminal, $operationUuid, $localRecordedAt, BoardingFunctionalCode::LegNotActive);
            }

            $segment->update(['status' => ReservationSegmentStatus::Boarded]);

            return BoardingEvent::query()->create([
                'ticket_id' => $ticket->id,
                'departure_id' => $departure->id,
                'terminal_p501_id' => $terminal->id,
                'operation_uuid' => $operationUuid,
                'local_recorded_at' => $localRecordedAt,
                'server_recorded_at' => now(),
                'status' => BoardingEventStatus::Accepted,
            ]);
        });

        // Publié après la transaction : le conflit est alors définitif. Un
        // événement retrouvé (rejeu) n'est pas « recréé » et ne republie rien ;
        // la déduplication de l'événement couvre de toute façon le retry.
        if ($event->wasRecentlyCreated && $event->status === BoardingEventStatus::Conflict) {
            $this->notifier->scanConflict($event, $departure, $terminal);
        }

        return $event;
    }

    private function handleAlreadyBoarded(
        Ticket $ticket,
        ReservationSegment $segment,
        Departure $departure,
        TerminalP501 $terminal,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
    ): BoardingEvent {
        $previousAccepted = BoardingEvent::query()
            ->where('departure_id', $departure->id)
            ->where('ticket_id', $ticket->id)
            ->where('status', BoardingEventStatus::Accepted)
            ->latest('server_recorded_at')
            ->first();

        // Même terminal (ou événement d'origine introuvable, ex. segment
        // embarqué par un autre moyen) : simple doublon. Terminal
        // différent : conflit — deux scans hors ligne, résolu de façon
        // déterministe par le verrou transactionnel ci-dessus (le premier
        // à valider la transaction gagne, quel que soit l'ordre d'arrivée
        // réseau des deux terminaux).
        $isCrossTerminalConflict = $previousAccepted !== null
            && $previousAccepted->terminal_p501_id !== null
            && $previousAccepted->terminal_p501_id !== $terminal->id;

        if ($isCrossTerminalConflict) {
            return BoardingEvent::query()->create([
                'ticket_id' => $ticket->id,
                'departure_id' => $departure->id,
                'terminal_p501_id' => $terminal->id,
                'operation_uuid' => $operationUuid,
                'local_recorded_at' => $localRecordedAt,
                'server_recorded_at' => now(),
                'status' => BoardingEventStatus::Conflict,
                'conflict_payload' => [
                    'code' => BoardingFunctionalCode::Conflict->value,
                    'message' => BoardingFunctionalCode::Conflict->message(),
                    'accepted_event_uuid' => $previousAccepted->uuid,
                    'accepted_terminal_uuid' => $previousAccepted->terminal?->uuid,
                    // ✅ CORRECTION : local_recorded_at est un Carbon non-nullable, on utilise ->
                    'accepted_local_recorded_at' => $previousAccepted->local_recorded_at->toISOString(),
                    'conflicting_terminal_uuid' => $terminal->uuid,
                    'conflicting_local_recorded_at' => $localRecordedAt->toISOString(),
                ],
            ]);
        }

        return $this->reject($ticket, $departure, $terminal, $operationUuid, $localRecordedAt, BoardingFunctionalCode::AlreadyBoarded);
    }

    private function reject(
        Ticket $ticket,
        Departure $departure,
        TerminalP501 $terminal,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
        BoardingFunctionalCode $code,
    ): BoardingEvent {
        return BoardingEvent::query()->create([
            'ticket_id' => $ticket->id,
            'departure_id' => $departure->id,
            'terminal_p501_id' => $terminal->id,
            'operation_uuid' => $operationUuid,
            'local_recorded_at' => $localRecordedAt,
            'server_recorded_at' => now(),
            'status' => BoardingEventStatus::Rejected,
            'conflict_payload' => [
                'code' => $code->value,
                'message' => $code->message(),
            ],
        ]);
    }
}
