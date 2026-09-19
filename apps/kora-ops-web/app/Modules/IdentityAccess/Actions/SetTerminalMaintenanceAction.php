<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;

class SetTerminalMaintenanceAction
{
    public function __construct(
        private RevokeTerminalSessionsAction $revokeSessions,
    ) {}

    /**
     * Met le terminal en maintenance et ferme ses sessions OPS actives : un
     * terminal en maintenance ne doit plus servir à scanner ni à synchroniser
     * tant qu'une nouvelle session n'a pas été ouverte après son retour.
     */
    public function execute(TerminalP501 $terminal, User $actor): TerminalP501
    {
        $previousStatus = $terminal->status;

        $terminal->update(['status' => TerminalP501Status::Maintenance]);

        $revokedCount = $this->revokeSessions->execute($terminal, OpsSessionRevocationReason::TerminalMaintenance->value);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties([
                'from' => $previousStatus->value,
                'to' => TerminalP501Status::Maintenance->value,
                'revoked_sessions' => $revokedCount,
            ])
            ->log('terminal_maintenance_set');

        return $terminal->refresh();
    }
}
