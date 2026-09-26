<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalActivityType;
use App\Modules\IdentityAccess\Models\TerminalP501;

class RecordTerminalSyncAction
{
    public function __construct(
        private LogTerminalActivityAction $logActivity,
    ) {}

    /**
     * Marque une synchronisation réussie pour le terminal (met à jour
     * last_sync_at, distinct de last_handshake_at) et journalise
     * l'événement. Le traitement métier des lots de synchronisation
     * (sync_batches / sync_operations) n'est pas du ressort de ce ticket.
     *
     * @param  array<string, mixed>  $summary
     * @param  array{hardware_model?: string|null, os_version?: string|null, app_version?: string|null}  $telemetry
     */
    public function execute(
        TerminalP501 $terminal,
        User $user,
        array $summary = [],
        array $telemetry = [],
    ): TerminalP501 {
        $terminal->update([
            'last_sync_at' => now(),
            ...array_filter($telemetry, static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);

        $this->logActivity->execute($terminal, $user, TerminalActivityType::Sync, $summary);

        return $terminal->refresh();
    }
}
