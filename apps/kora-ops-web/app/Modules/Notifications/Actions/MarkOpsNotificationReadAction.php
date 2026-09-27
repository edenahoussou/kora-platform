<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Models\OpsNotification;

class MarkOpsNotificationReadAction
{
    /**
     * Marque une notification lue. Idempotent (la date de première lecture est
     * conservée). Une notification qui n'appartient pas à l'utilisateur est
     * introuvable (404) : son existence n'est jamais révélée.
     */
    public function execute(User $user, string $notificationUuid): OpsNotification
    {
        $notification = OpsNotification::query()
            ->whereKey($notificationUuid)
            ->where('audience', 'ops')
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->load(['event.departure.line', 'event.terminal']);
    }
}
