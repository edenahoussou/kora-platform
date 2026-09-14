<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalActivityType;
use App\Modules\IdentityAccess\Models\TerminalP501;

use function activity;

class LogTerminalActivityAction
{
    /**
     * Journalise un scan, une impression, une erreur, un handshake ou une
     * synchronisation pour un terminal donné (spatie/laravel-activitylog).
     *
     * @param  array<string, mixed>  $context
     */
    public function execute(
        TerminalP501 $terminal,
        User $user,
        TerminalActivityType $type,
        array $context = [],
    ): void {
        activity()
            ->performedOn($terminal)
            ->causedBy($user)
            ->withProperties(array_merge($context, ['serial_number' => $terminal->serial_number]))
            ->log("terminal_{$type->value}");
    }
}
