<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;

class DeactivateTerminalAction
{
    public function __construct(
        private RevokeTerminalSessionsAction $revokeSessions,
    ) {}

    public function execute(TerminalP501 $terminal, User $actor): TerminalP501
    {
        $previousStatus = $terminal->status;

        $terminal->update(['status' => TerminalP501Status::Inactive]);

        $revokedCount = $this->revokeSessions->execute($terminal, OpsSessionRevocationReason::TerminalDeactivated->value);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties([
                'from' => $previousStatus->value,
                'to' => TerminalP501Status::Inactive->value,
                'revoked_sessions' => $revokedCount,
            ])
            ->log('terminal_deactivated');

        return $terminal->refresh();
    }
}
