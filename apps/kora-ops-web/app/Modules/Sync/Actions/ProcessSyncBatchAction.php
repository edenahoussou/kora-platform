<?php

namespace App\Modules\Sync\Actions;

use App\Models\User;
use App\Modules\Boarding\Actions\ProcessBoardingScanAction;
use App\Modules\Boarding\Actions\ProcessIndividualNoShowAction;
use App\Modules\Boarding\Actions\ProcessSeatReleaseAction;
use App\Modules\Boarding\Exceptions\BoardingTicketUnknownException;
use App\Modules\Boarding\Exceptions\SeatReleaseTicketUnknownException;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent as SeatReleaseEventModel;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Actions\RecordTerminalSyncAction;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Sync\Data\SyncBatchStatus;
use App\Modules\Sync\Data\SyncOperationStatus;
use App\Modules\Sync\Data\SyncOperationType;
use App\Modules\Sync\Models\SyncBatch;
use App\Modules\Sync\Models\SyncOperation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Point d'entrée unique pour un lot d'opérations terrain (embarquement,
 * no-show, libération). Idempotent à deux niveaux : le lot lui-même
 * (batch_uuid fourni par le client) et chaque opération (operation_uuid)
 * — un lot rejoué, en tout ou partie, ne crée jamais de doublon.
 *
 * Les libérations de segment (seat_release, boarding.no_show) sont
 * traitées avant les embarquements (boarding.scan) : elles affectent la
 * disponibilité et la revente, donc le plus urgent à répercuter.
 */
class ProcessSyncBatchAction
{
    public function __construct(
        private ProcessBoardingScanAction $processBoardingScan,
        private ProcessSeatReleaseAction $processSeatRelease,
        private ProcessIndividualNoShowAction $processIndividualNoShow,
        private RecordTerminalSyncAction $recordTerminalSync,
        private OpsNotifier $notifier,
    ) {}

    /**
     * @param  array<int, array{operation_uuid: string, operation_type: string, local_recorded_at: string, payload: array<string, mixed>}>  $operations
     */
    public function execute(TerminalP501 $terminal, User $agent, string $batchUuid, array $operations): SyncBatch
    {
        $batch = SyncBatch::query()->where('uuid', $batchUuid)->first();

        // Lot déjà traité (ou en cours) : rejoué à l'identique, jamais retraité.
        if ($batch !== null && $batch->status !== SyncBatchStatus::Failed) {
            return $batch->load('operations');
        }

        if ($batch !== null) {
            // Rejeu d'un lot ÉCHOUÉ : mêmes opérations, idempotentes une à une —
            // celles déjà traitées sont reprises telles quelles, les autres
            // (restées sans résultat) sont traitées à ce tour.
            $batch->update(['status' => SyncBatchStatus::Pending, 'processed_at' => null, 'summary' => null]);
        } else {
            $batch = SyncBatch::query()->create([
                'uuid' => $batchUuid,
                'terminal_p501_id' => $terminal->id,
                'submitted_by' => $agent->id,
                'status' => SyncBatchStatus::Pending,
                'submitted_at' => now(),
            ]);
        }

        try {
            $ordered = collect($operations)->sortBy(
                fn (array $op) => SyncOperationType::from($op['operation_type'])->priority(),
            )->values();

            $tally = ['accepted' => 0, 'pending' => 0, 'conflict' => 0, 'rejected' => 0];
            $processedOperations = collect();

            foreach ($ordered as $op) {
                $syncOperation = $this->processOne($batch, $terminal, $agent, $op);
                $tally[$syncOperation->status->value]++;
                $processedOperations->push($syncOperation);
            }

            $batch->update([
                'status' => SyncBatchStatus::Completed,
                'processed_at' => now(),
                'summary' => $tally,
            ]);

            $this->recordTerminalSync->execute($terminal, $agent, $tally);
        } catch (Throwable $exception) {
            // Le lot est marqué échoué (rejouable) et l'opérateur alerté ; l'erreur
            // reste remontée : le terminal réessaiera avec le même batch_uuid.
            $batch->update(['status' => SyncBatchStatus::Failed, 'processed_at' => now()]);
            $this->notifier->syncFailed($batch, $terminal, $agent);

            throw $exception;
        }

        $this->notifySyncOutcome($batch, $terminal, $agent, $tally, $processedOperations);

        // Une opération réutilisée (déjà vue dans un AUTRE lot, ex. après
        // un crash-retry avec un nouveau batch_uuid) reste rattachée en
        // base à son lot d'origine (sync_batch_id) — ce n'est pas une
        // erreur, c'est justement l'idempotence par opération. Mais la
        // réponse de CE lot doit quand même refléter tout ce qui a été
        // traité pendant CETTE requête : on fixe la relation explicitement
        // plutôt que de recharger depuis la base (qui ne verrait que les
        // opérations réellement liées par sync_batch_id à ce lot précis).
        return $batch->setRelation('operations', $processedOperations);
    }

    /**
     * Notifie l'issue du lot : « synchronisation terminée » au convoyeur qui
     * l'a soumis et, s'il y en a, UNE notification « opérations rejetées »
     * pour tout le lot (pas une par opération).
     *
     * @param  array<string, int>  $tally
     * @param  Collection<int, SyncOperation>  $processedOperations
     */
    private function notifySyncOutcome(SyncBatch $batch, TerminalP501 $terminal, User $agent, array $tally, Collection $processedOperations): void
    {
        $this->notifier->syncCompleted($batch, $terminal, $agent, $tally);

        $rejected = $processedOperations->filter(
            fn (SyncOperation $operation): bool => $operation->status === SyncOperationStatus::Rejected,
        )->values();

        if ($rejected->isNotEmpty()) {
            $this->notifier->operationsRejected($batch, $terminal, $agent, $rejected);
        }
    }

    /**
     * @param  array{operation_uuid: string, operation_type: string, local_recorded_at: string, payload: array<string, mixed>}  $op
     */
    private function processOne(SyncBatch $batch, TerminalP501 $terminal, User $agent, array $op): SyncOperation
    {
        // Idempotence par opération : une même operation_uuid déjà vue
        // (dans ce lot ou un précédent) n'est jamais retraitée.
        $existing = SyncOperation::query()->where('operation_uuid', $op['operation_uuid'])->first();

        // Une opération qui a un résultat n'est jamais retraitée. Sans résultat,
        // elle est restée en plan (lot échoué en cours de route) : on la traite.
        if ($existing !== null && $existing->result !== null) {
            return $existing;
        }

        $type = SyncOperationType::from($op['operation_type']);
        $localRecordedAt = Carbon::parse($op['local_recorded_at']);

        $syncOperation = $existing ?? SyncOperation::query()->create([
            'sync_batch_id' => $batch->id,
            'operation_uuid' => $op['operation_uuid'],
            'operation_type' => $type,
            'local_recorded_at' => $localRecordedAt,
            'server_received_at' => now(),
            'status' => SyncOperationStatus::Pending,
            'payload' => $op['payload'],
        ]);

        $result = $this->dispatch($type, $terminal, $agent, $op['payload'], $op['operation_uuid'], $localRecordedAt);

        $syncOperation->update([
            'status' => $result['status'],
            'result' => $result,
        ]);

        return $syncOperation->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatch(
        SyncOperationType $type,
        TerminalP501 $terminal,
        User $agent,
        array $payload,
        string $operationUuid,
        CarbonInterface $localRecordedAt,
    ): array {
        $departure = isset($payload['departure_uuid'])
            ? Departure::query()->where('uuid', $payload['departure_uuid'])->first()
            : null;

        if ($departure === null) {
            return $this->genericRejection('invalid_payload', 'Le champ departure_uuid est manquant ou invalide.', 'Vérifiez le payload de l’opération et réessayez.');
        }

        try {
            return match ($type) {
                SyncOperationType::BoardingScan => $this->dispatchBoardingScan($departure, $terminal, $agent, $payload, $operationUuid, $localRecordedAt),
                SyncOperationType::BoardingNoShow => $this->dispatchIndividualNoShow($departure, $terminal, $agent, $payload, $operationUuid, $localRecordedAt),
                SyncOperationType::SeatRelease => $this->dispatchSeatRelease($departure, $terminal, $agent, $payload, $operationUuid, $localRecordedAt),
            };
        } catch (BoardingTicketUnknownException|SeatReleaseTicketUnknownException $e) {
            return $this->genericRejection('ticket_unknown', $e->getMessage(), 'Vérifiez le billet et réessayez.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchBoardingScan(Departure $departure, TerminalP501 $terminal, User $agent, array $payload, string $operationUuid, CarbonInterface $localRecordedAt): array
    {
        if (! Gate::forUser($agent)->check('create', BoardingEvent::class) || ! Gate::forUser($agent)->check('scanBoarding', $departure)) {
            return $this->genericRejection('permission_denied', 'Permission refusée pour cette opération.', 'Vérifiez les droits de l’utilisateur.');
        }

        $event = $this->processBoardingScan->execute(
            $departure,
            $terminal,
            $operationUuid,
            $localRecordedAt,
            $payload['qr_payload'] ?? null,
            $payload['short_code'] ?? null,
        );

        $details = $event->conflict_payload;

        return [
            'status' => $event->status->value,
            'code' => $details['code'] ?? null,
            'message' => $details['message'] ?? null,
            'recommendedAction' => $details['recommendedAction'] ?? null,
            'entityType' => 'boarding_event',
            'entityUuid' => $event->uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchIndividualNoShow(Departure $departure, TerminalP501 $terminal, User $agent, array $payload, string $operationUuid, CarbonInterface $localRecordedAt): array
    {
        if (! Gate::forUser($agent)->check('create', SeatReleaseEventModel::class) || ! Gate::forUser($agent)->check('releaseSeat', $departure)) {
            return $this->genericRejection('permission_denied', 'Permission refusée pour cette opération.', 'Vérifiez les droits de l’utilisateur.');
        }

        if (! isset($payload['ticket_uuid'])) {
            return $this->genericRejection('invalid_payload', 'Le champ ticket_uuid est manquant.', 'Vérifiez le payload de l’opération et réessayez.');
        }

        $event = $this->processIndividualNoShow->execute($departure, $terminal, $agent, $payload['ticket_uuid'], $operationUuid, $localRecordedAt);

        return $this->seatReleaseResult($event);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchSeatRelease(Departure $departure, TerminalP501 $terminal, User $agent, array $payload, string $operationUuid, CarbonInterface $localRecordedAt): array
    {
        if (! Gate::forUser($agent)->check('create', SeatReleaseEventModel::class) || ! Gate::forUser($agent)->check('releaseSeat', $departure)) {
            return $this->genericRejection('permission_denied', 'Permission refusée pour cette opération.', 'Vérifiez les droits de l’utilisateur.');
        }

        if (! isset($payload['ticket_uuid'], $payload['actual_stop_uuid'])) {
            return $this->genericRejection('invalid_payload', 'Les champs ticket_uuid et actual_stop_uuid sont requis.', 'Vérifiez le payload de l’opération et réessayez.');
        }

        $actualStop = Stop::query()->where('uuid', $payload['actual_stop_uuid'])->first();
        if ($actualStop === null) {
            return $this->genericRejection('invalid_payload', 'actual_stop_uuid est invalide.', 'Vérifiez le payload de l’opération et réessayez.');
        }

        $event = $this->processSeatRelease->execute($departure, $terminal, $agent, $payload['ticket_uuid'], $actualStop, $operationUuid, $localRecordedAt);

        return $this->seatReleaseResult($event);
    }

    /**
     * @return array<string, mixed>
     */
    private function seatReleaseResult(SeatReleaseEventModel $event): array
    {
        $details = $event->conflict_payload;

        return [
            'status' => $event->status->value,
            'code' => $details['code'] ?? null,
            'message' => $details['message'] ?? null,
            'recommendedAction' => $details['recommendedAction'] ?? null,
            'entityType' => 'seat_release_event',
            'entityUuid' => $event->uuid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericRejection(string $code, string $message, string $recommendedAction): array
    {
        return [
            'status' => SyncOperationStatus::Rejected->value,
            'code' => $code,
            'message' => $message,
            'recommendedAction' => $recommendedAction,
            'entityType' => null,
            'entityUuid' => null,
        ];
    }
}
