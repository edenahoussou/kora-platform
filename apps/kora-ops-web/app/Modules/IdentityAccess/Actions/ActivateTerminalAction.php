<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;

class ActivateTerminalAction
{
    public function execute(TerminalP501 $terminal, User $actor): TerminalP501
    {
        $previousStatus = $terminal->status;

        $terminal->update(['status' => TerminalP501Status::Active]);

        activity()
            ->performedOn($terminal)
            ->causedBy($actor)
            ->withProperties(['from' => $previousStatus->value, 'to' => TerminalP501Status::Active->value])
            ->log('terminal_activated');

        return $terminal->refresh();
    }
}
