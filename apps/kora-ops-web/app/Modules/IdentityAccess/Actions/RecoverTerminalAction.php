<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function activity;

class RecoverTerminalAction
{
    /**
     * @param  array{reason: string, assigned_user_id?: int|null, agency_id?: int|null}  $data
     */
    public function execute(TerminalP501 $terminal, array $data, User $actor): TerminalP501
    {
        if ($terminal->status !== TerminalP501Status::Lost) {
            throw ValidationException::withMessages([
                'terminal' => 'Seul un terminal déclaré perdu peut être récupéré.',
            ]);
        }

        return DB::transaction(function () use ($terminal, $data, $actor): TerminalP501 {
            TerminalAssignment::query()
                ->where('terminal_p501_id', $terminal->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now(), 'reason' => $data['reason']]);

            $assignedUserId = $data['assigned_user_id'] ?? null;
            $agencyId = $data['agency_id'] ?? null;

            if ($assignedUserId !== null || $agencyId !== null) {
                TerminalAssignment::query()->create([
                    'terminal_p501_id' => $terminal->id,
                    'assigned_user_id' => $assignedUserId,
                    'agency_id' => $agencyId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'reason' => $data['reason'],
                ]);
            }

            $terminal->update([
                'status' => TerminalP501Status::Active,
                'assigned_user_id' => $assignedUserId,
                'agency_id' => $agencyId,
            ]);

            activity()
                ->performedOn($terminal)
                ->causedBy($actor)
                ->withProperties([
                    'from' => TerminalP501Status::Lost->value,
                    'to' => TerminalP501Status::Active->value,
                    'reason' => $data['reason'],
                    'assigned_user_id' => $assignedUserId,
                    'agency_id' => $agencyId,
                ])
                ->log('terminal_recovered');

            return $terminal->refresh()->load(['assignedUser', 'agency']);
        });
    }
}
