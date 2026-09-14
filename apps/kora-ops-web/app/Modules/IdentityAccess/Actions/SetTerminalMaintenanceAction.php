<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;

class SetTerminalMaintenanceAction
{
    public function execute(TerminalP501 $terminal, User $actor): TerminalP501
    {
        $previousStatus = $terminal->status;

        $terminal->update(['status' => TerminalP501Status::Maintenance]);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties(['from' => $previousStatus->value, 'to' => TerminalP501Status::Maintenance->value])
            ->log('terminal_maintenance_set');

        return $terminal->refresh();
    }
}
