<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;

class ReportTerminalLostAction
{
    public function __construct(
        private RevokeTerminalSessionsAction $revokeSessions,
    ) {}

    public function execute(TerminalP501 $terminal, User $actor, ?string $reason = null): TerminalP501
    {
        $previousStatus = $terminal->status;

        $terminal->update(['status' => TerminalP501Status::Lost]);

        $revokedCount = $this->revokeSessions->execute($terminal, OpsSessionRevocationReason::TerminalLost->value);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties([
                'from' => $previousStatus->value,
                'to' => TerminalP501Status::Lost->value,
                'revoked_sessions' => $revokedCount,
                'reason' => $reason,
            ])
            ->log('terminal_lost');

        return $terminal->refresh();
    }
}
