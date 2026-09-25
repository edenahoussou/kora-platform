<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\IncidentType;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Notifications\Actions\PublishOpsNotificationAction;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Sync\Models\SyncBatch;
use App\Modules\Sync\Models\SyncOperation;
use Illuminate\Support\Collection;

/**
 * Point d'entrée unique des alertes opérationnelles boarding : chaque méthode
 * correspond à un événement métier, construit son identifiant de
 * déduplication et — surtout — la liste blanche des données publiées.
 *
 * Règle de confidentialité : `data` ne contient que des UUID, des codes
 * métier, des numéros de série de terminal et des compteurs. Jamais de
 * montant, de prix, de moyen de paiement ni de donnée passager.
 *
 * Aucune méthode ne lève : la publication est isolée de l'opération métier
 * qui la déclenche (voir PublishOpsNotificationAction).
 */
class OpsNotifier
{
    private const array TERMINAL_REVOCATION_LABELS = [
        'terminal_deactivated' => 'terminal désactivé',
        'terminal_lost' => 'terminal déclaré perdu',
        'terminal_maintenance' => 'terminal en maintenance',
        'terminal_reassigned' => 'terminal réaffecté',
    ];

    private const array PRINT_ERROR_LABELS = [
        'out_of_paper' => 'plus de papier',
        'not_connected' => 'imprimante déconnectée',
        'overheated' => 'imprimante en surchauffe',
        'unknown' => 'erreur matérielle',
    ];

    private const array DEPARTURE_CHANGE_LABELS = [
        'date' => 'date',
        'time' => 'heure',
        'bus' => 'bus',
        'line' => 'ligne',
        'cancelled' => 'annulation',
        'crew_removed' => 'équipage',
    ];

    public function __construct(
        private PublishOpsNotificationAction $publisher,
        private OpsNotificationRecipientResolver $resolver,
    ) {}

    /**
     * Le convoyeur (ou un membre d'équipage) vient d'être affecté au départ.
     */
    public function departureAssigned(Departure $departure, User $assigned): void
    {
        $departure->loadMissing('line');

        $role = match ($assigned->id) {
            $departure->conductor_id => 'conductor',
            $departure->driver_id => 'driver',
            $departure->secondary_driver_id => 'second_driver',
            $departure->mechanic_id => 'mechanic',
            default => 'crew',
        };

        $this->publish(
            OpsNotificationType::DepartureAssigned,
            sprintf('departure_assigned:%s:%s:%d', $departure->uuid, $assigned->uuid, $this->stamp($departure)),
            sprintf('Vous êtes affecté au départ %s.', $this->describe($departure)),
            ['crew_role' => $role],
            new Collection([$assigned]),
            $assigned,
            $departure,
        );
    }

    /**
     * Le départ a changé (date, heure, bus, ligne, annulation, équipage retiré).
     *
     * @param  list<string>  $changes  Codes : date, time, bus, line, cancelled, crew_removed.
     * @param  Collection<int, User>  $removedCrew  Membres retirés de l'équipage (informés eux aussi).
     */
    public function departureModified(Departure $departure, array $changes, Collection $removedCrew): void
    {
        $departure->loadMissing('line');

        $labels = array_map(
            static fn (string $change): string => self::DEPARTURE_CHANGE_LABELS[$change] ?? $change,
            $changes,
        );

        $this->publish(
            OpsNotificationType::DepartureModified,
            sprintf('departure_modified:%s:%s:%d', $departure->uuid, sha1(implode(',', $changes).'|'.$this->signature($departure)), $this->stamp($departure)),
            sprintf('Le départ %s a été modifié (%s).', $this->describe($departure), implode(', ', $labels)),
            ['changes' => $changes, 'departure_status' => $departure->status->value],
            $this->resolver->crewOf($departure)->merge($removedCrew),
            $this->subjectOf($departure),
            $departure,
        );
    }

    /**
     * Le manifeste du départ a changé côté serveur (vente, annulation…) :
     * le convoyeur doit l'actualiser.
     */
    public function manifestStale(Departure $departure, string $manifestVersion): void
    {
        $departure->loadMissing('line');

        $this->publish(
            OpsNotificationType::ManifestStale,
            sprintf('manifest_stale:%s:%s', $departure->uuid, $manifestVersion),
            sprintf('Le manifeste du départ %s a changé. Actualisez-le avant de continuer.', $this->describe($departure)),
            ['manifest_version' => $manifestVersion],
            $this->resolver->crewOf($departure),
            $this->subjectOf($departure),
            $departure,
        );
    }

    /**
     * Les sessions d'un terminal ont été fermées (désactivé, perdu, en
     * maintenance, réaffecté) : le convoyeur doit se reconnecter.
     */
    public function terminalRevoked(TerminalSession $session, string $reason): void
    {
        $session->loadMissing(['terminal', 'user']);

        $terminal = $session->terminal;
        $label = self::TERMINAL_REVOCATION_LABELS[$reason] ?? $reason;

        $this->publish(
            OpsNotificationType::TerminalRevoked,
            'terminal_revoked:'.$session->uuid,
            sprintf('Le terminal %s n’est plus autorisé (%s). Reconnectez-vous pour continuer.', $terminal->serial_number, $label),
            ['reason' => $reason, 'serial_number' => $terminal->serial_number],
            new Collection([$session->user]),
            $session->user,
            null,
            $terminal,
        );
    }

    /**
     * Un même billet a été scanné sur deux terminaux (deux scans hors ligne).
     */
    public function scanConflict(BoardingEvent $event, Departure $departure, TerminalP501 $terminal): void
    {
        $departure->loadMissing('line');
        $event->loadMissing('ticket');
        $terminal->loadMissing('assignedUser');

        $payload = $event->conflict_payload ?? [];
        $acceptedSerial = isset($payload['accepted_terminal_uuid']) && is_string($payload['accepted_terminal_uuid'])
            ? TerminalP501::query()->where('uuid', $payload['accepted_terminal_uuid'])->value('serial_number')
            : null;

        $this->publish(
            OpsNotificationType::ScanConflict,
            'scan_conflict:'.$event->uuid,
            sprintf(
                'Un billet du départ %s a été scanné sur deux terminaux (%s et %s). Vérifiez l’embarquement du passager.',
                $this->describe($departure),
                $acceptedSerial ?? 'terminal inconnu',
                $terminal->serial_number,
            ),
            [
                'code' => is_string($payload['code'] ?? null) ? $payload['code'] : 'conflict',
                'boarding_event_uuid' => $event->uuid,
                'accepted_event_uuid' => is_string($payload['accepted_event_uuid'] ?? null) ? $payload['accepted_event_uuid'] : null,
                'ticket_uuid' => $event->ticket?->uuid,
                'accepted_terminal_serial' => $acceptedSerial,
                'conflicting_terminal_serial' => $terminal->serial_number,
            ],
            $this->resolver->crewOf($departure),
            $terminal->assignedUser ?? $this->subjectOf($departure),
            $departure,
            $terminal,
        );
    }

    /**
     * Lot de synchronisation traité.
     *
     * @param  array<string, int>  $tally  accepted, pending, conflict, rejected.
     */
    public function syncCompleted(SyncBatch $batch, TerminalP501 $terminal, User $submitter, array $tally): void
    {
        $this->publish(
            OpsNotificationType::SyncCompleted,
            'sync_completed:'.$batch->uuid,
            sprintf(
                'Synchronisation terminée : %d accepté(s), %d en attente, %d conflit(s), %d rejeté(s).',
                $tally['accepted'] ?? 0,
                $tally['pending'] ?? 0,
                $tally['conflict'] ?? 0,
                $tally['rejected'] ?? 0,
            ),
            ['batch_uuid' => $batch->uuid, 'summary' => $tally],
            new Collection([$submitter]),
            $submitter,
            null,
            $terminal,
        );
    }

    /**
     * Le traitement du lot a échoué (il peut être rejoué avec le même identifiant).
     */
    public function syncFailed(SyncBatch $batch, TerminalP501 $terminal, User $submitter): void
    {
        $this->publish(
            OpsNotificationType::SyncFailed,
            'sync_failed:'.$batch->uuid,
            sprintf('La synchronisation du lot %s a échoué. Relancez-la : les opérations déjà traitées ne seront pas dupliquées.', substr($batch->uuid, 0, 8)),
            ['batch_uuid' => $batch->uuid],
            new Collection([$submitter]),
            $submitter,
            null,
            $terminal,
        );
    }

    /**
     * Des opérations d'un lot rejouées hors ligne ont été rejetées (une seule
     * notification par lot, pas une par opération).
     *
     * @param  Collection<int, SyncOperation>  $rejected
     */
    public function operationsRejected(SyncBatch $batch, TerminalP501 $terminal, User $submitter, Collection $rejected): void
    {
        $operations = $rejected->take(20)->map(fn (SyncOperation $operation): array => [
            'operation_uuid' => $operation->operation_uuid,
            'operation_type' => $operation->operation_type->value,
            'code' => is_array($operation->result) && is_string($operation->result['code'] ?? null) ? $operation->result['code'] : null,
        ])->values()->all();

        $this->publish(
            OpsNotificationType::OperationRejected,
            'operation_rejected:'.$batch->uuid,
            sprintf('%d opération(s) rejetée(s) lors de la synchronisation. Consultez le détail pour les corriger.', $rejected->count()),
            ['batch_uuid' => $batch->uuid, 'rejected_count' => $rejected->count(), 'operations' => $operations],
            new Collection([$submitter]),
            $submitter,
            null,
            $terminal,
        );
    }

    /**
     * La clôture no-show du départ a libéré des sièges.
     */
    public function noShowsClosed(Departure $departure, ?TerminalP501 $terminal, User $agent, int $closedCount, string $closureOperationUuid): void
    {
        $departure->loadMissing('line');

        $this->publish(
            OpsNotificationType::NoShowClosed,
            'no_show_closed:'.$closureOperationUuid,
            sprintf('%d no-show clôturé(s) pour le départ %s.', $closedCount, $this->describe($departure)),
            ['closed_count' => $closedCount],
            $this->resolver->crewOf($departure),
            $agent,
            $departure,
            $terminal,
        );
    }

    /**
     * Le bus est parti (statut `departed`), quel que soit le chemin qui a
     * changé le statut (convoyeur sur le terminal ou OPS Web). Publié une
     * seule fois par départ. Information : équipage et directeurs des
     * opérations de l'agence du convoyeur, jamais d'e-mail.
     */
    public function departureDeparted(Departure $departure): void
    {
        $departure->loadMissing('line');

        $this->publish(
            OpsNotificationType::DepartureDeparted,
            'departure_departed:'.$departure->uuid,
            sprintf('Le bus du départ %s est parti.', $this->describe($departure)),
            ['departed_at' => $departure->departed_at?->toISOString()],
            $this->resolver->crewOf($departure),
            $this->subjectOf($departure),
            $departure,
        );
    }

    /**
     * Un convoyeur signale un incident. `accident` et `security` sont des
     * alertes critiques (non désactivables, bloquantes sur le mobile), les
     * autres types des avertissements. Alerte : e-mail aux directeurs des
     * opérations de l'agence du convoyeur (To) et aux super-administrateurs
     * (Cc), et notification à l'équipage du départ.
     */
    public function incidentReported(Incident $incident, Departure $departure, User $reporter, ?TerminalP501 $terminal): void
    {
        $departure->loadMissing('line');

        $critical = in_array($incident->type, [IncidentType::Accident, IncidentType::Security], true);
        $label = match ($incident->type) {
            IncidentType::Breakdown => 'panne',
            IncidentType::Delay => 'retard',
            IncidentType::Accident => 'accident',
            IncidentType::Security => 'sécurité',
            IncidentType::Other => 'autre',
        };

        $this->publish(
            $critical ? OpsNotificationType::IncidentCritical : OpsNotificationType::IncidentReported,
            'incident_reported:'.$incident->uuid,
            sprintf(
                'Incident (%s) signalé par %s sur le départ %s : %s',
                $label,
                $reporter->name,
                $this->describe($departure),
                mb_strimwidth($incident->description, 0, 200, '…'),
            ),
            ['incident_uuid' => $incident->uuid, 'incident_type' => $incident->type->value],
            $this->resolver->crewOf($departure)->push($reporter),
            $reporter,
            $departure,
            $terminal,
        );
    }

    /**
     * Le terminal signale un échec d'impression (exemplaire du manifeste
     * frontière, ticket, PDF). Sans effet pour une activité qui n'est pas un échec.
     *
     * @param  array<string, mixed>  $context  Contexte de l'activité `print` envoyée par le mobile.
     */
    public function printError(TerminalP501 $terminal, User $user, array $context): void
    {
        $result = $context['result'] ?? null;

        if (! in_array($result, ['copy_failed', 'pdf_failed'], true)) {
            return;
        }

        $kind = $this->printErrorKind($context['error_kind'] ?? null);
        $copyNumber = is_int($context['copy_number'] ?? null) ? $context['copy_number'] : null;
        $jobUuid = is_string($context['job_uuid'] ?? null) ? $context['job_uuid'] : '';
        $recordedAt = is_string($context['recorded_at'] ?? null) ? $context['recorded_at'] : '';

        // Le départ n'est rattaché que si l'utilisateur en fait partie : jamais
        // de lien vers le départ d'un autre.
        $departure = null;
        if (is_string($context['departure_uuid'] ?? null)) {
            $candidate = Departure::query()->where('uuid', $context['departure_uuid'])->first();
            $departure = $candidate !== null && $this->resolver->crewOf($candidate)->contains('id', $user->id) ? $candidate : null;
        }

        $this->publish(
            OpsNotificationType::PrintError,
            'print_error:'.$terminal->uuid.':'.sha1($jobUuid.'|'.$copyNumber.'|'.$recordedAt.'|'.(string) $result),
            sprintf('L’impression a échoué sur le terminal %s (%s).', $terminal->serial_number, self::PRINT_ERROR_LABELS[$kind]),
            [
                'result' => $result,
                'error_kind' => $kind,
                'copy_number' => $copyNumber,
                'copies_confirmed' => is_int($context['copies_confirmed'] ?? null) ? $context['copies_confirmed'] : null,
                'mode' => is_string($context['mode'] ?? null) ? substr($context['mode'], 0, 24) : null,
            ],
            new Collection([$user]),
            $user,
            $departure,
            $terminal,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, User>  $recipients
     */
    private function publish(
        OpsNotificationType $type,
        string $dedupeKey,
        string $message,
        array $data,
        Collection $recipients,
        ?User $subject,
        ?Departure $departure = null,
        ?TerminalP501 $terminal = null,
    ): void {
        $this->publisher->execute(new OpsNotificationDraft(
            $type,
            $dedupeKey,
            $message,
            $data,
            $recipients,
            $subject,
            $departure,
            $terminal,
            now(),
        ));
    }

    private function describe(Departure $departure): string
    {
        return sprintf(
            '%s du %s à %s',
            $departure->line->code ?? 'ligne',
            $departure->departure_date?->format('d/m/Y') ?? '—',
            substr((string) $departure->departure_time, 0, 5),
        );
    }

    /** Le convoyeur du départ (à défaut le chauffeur) : son agence désigne les directeurs destinataires. */
    private function subjectOf(Departure $departure): ?User
    {
        $id = $departure->conductor_id ?? $departure->driver_id;

        return $id === null ? null : User::query()->find($id);
    }

    /**
     * Valeurs brutes qui définissent la modification : deux changements de même
     * nature mais vers des valeurs différentes ne partagent jamais leur identifiant.
     */
    private function signature(Departure $departure): string
    {
        $attributes = $departure->getAttributes();

        return implode('|', array_map(
            static fn (string $column): string => (string) ($attributes[$column] ?? ''),
            ['departure_date', 'departure_time', 'bus_id', 'line_id', 'status', 'driver_id', 'secondary_driver_id', 'conductor_id', 'mechanic_id'],
        ));
    }

    private function stamp(Departure $departure): int
    {
        return $departure->updated_at?->getTimestamp() ?? 0;
    }

    private function printErrorKind(mixed $value): string
    {
        // Le mobile envoie le nom de l'enum Dart (outOfPaper…) : normalisé en snake_case.
        $normalized = is_string($value) ? strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value)) : 'unknown';

        return array_key_exists($normalized, self::PRINT_ERROR_LABELS) ? $normalized : 'unknown';
    }
}
