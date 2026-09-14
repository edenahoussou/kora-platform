<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TerminalAssignment;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Facades\DB;

class AssignTerminalAction
{
    /**
     * Affecte un terminal à un utilisateur terrain et/ou une agence.
     * Clôt l'affectation en cours (le cas échéant) et journalise la
     * nouvelle affectation dans l'historique.
     *
     * @param  array{assigned_user_id?: int|null, agency_id?: int|null, reason?: string|null}  $data
     */
    public function execute(TerminalP501 $terminal, array $data, User $actor): TerminalP501
    {
        return DB::transaction(function () use ($terminal, $data, $actor): TerminalP501 {
            TerminalAssignment::query()
                ->where('terminal_p501_id', $terminal->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            $assignedUserId = $data['assigned_user_id'] ?? null;
            $agencyId = $data['agency_id'] ?? null;

            TerminalAssignment::query()->create([
                'terminal_p501_id' => $terminal->id,
                'assigned_user_id' => $assignedUserId,
                'agency_id' => $agencyId,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'reason' => $data['reason'] ?? null,
            ]);

            $terminal->update([
                'assigned_user_id' => $assignedUserId,
                'agency_id' => $agencyId,
            ]);

            // refresh() ne recharge que les attributs, pas les relations déjà
            // chargées (ou non) sur l'instance — on charge explicitement
            // celles attendues par TerminalP501Resource (whenLoaded).
            return $terminal->refresh()->load(['assignedUser', 'agency']);
        });
    }
}
