<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionRevocationReason;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function activity;

class ReturnTerminalToStockAction
{
    public function __construct(
        private RevokeTerminalSessionsAction $revokeSessions,
    ) {}

    public function execute(TerminalP501 $terminal, string $reason, User $actor): TerminalP501
    {
        if ($terminal->status === TerminalP501Status::Lost) {
            throw ValidationException::withMessages([
                'terminal' => 'Récupérez le terminal perdu avant de le remettre au stock.',
            ]);
        }

        return DB::transaction(function () use ($terminal, $reason, $actor): TerminalP501 {
            TerminalAssignment::query()
                ->where('terminal_p501_id', $terminal->id)
                ->whereNull('unassigned_at')
                ->update([
                    'unassigned_at' => now(),
                    'reason' => $reason,
                ]);

            $previousStatus = $terminal->status;
            $terminal->update([
                'status' => TerminalP501Status::Inactive,
                'assigned_user_id' => null,
                'agency_id' => null,
            ]);

            $revokedCount = $this->revokeSessions->execute($terminal, OpsSessionRevocationReason::TerminalDeactivated->value);

            activity()
                ->performedOn($terminal)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $previousStatus->value,
                    'to' => TerminalP501Status::Inactive->value,
                    'reason' => $reason,
                    'revoked_sessions' => $revokedCount,
                ])
                ->log('terminal_returned_to_stock');

            return $terminal->refresh()->load(['assignedUser', 'agency']);
        });
    }
}
