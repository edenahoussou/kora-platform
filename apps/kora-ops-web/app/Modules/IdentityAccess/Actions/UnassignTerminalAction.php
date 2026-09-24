<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Facades\DB;

use function activity;

class UnassignTerminalAction
{
    public function __construct(
        private RevokeTerminalSessionsAction $revokeSessions,
    ) {}

    public function execute(TerminalP501 $terminal, string $reason, User $actor): TerminalP501
    {
        return DB::transaction(function () use ($terminal, $reason, $actor): TerminalP501 {
            $previousUserId = $terminal->assigned_user_id;

            TerminalAssignment::query()
                ->where('terminal_p501_id', $terminal->id)
                ->whereNull('unassigned_at')
                ->update([
                    'unassigned_at' => now(),
                    'reason' => $reason,
                ]);

            $terminal->update([
                'assigned_user_id' => null,
                'agency_id' => null,
            ]);

            if ($previousUserId !== null) {
                $this->revokeSessions->execute($terminal, OpsSessionRevocationReason::TerminalReassigned->value);
            }

            activity()
                ->performedOn($terminal)
                ->causedBy($actor)
                ->withProperties(['reason' => $reason, 'previous_user_id' => $previousUserId])
                ->log('terminal_unassigned');

            return $terminal->refresh()->load(['assignedUser', 'agency']);
        });
    }
}
