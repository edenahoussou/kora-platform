<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Models\OpsNotification;
use Illuminate\Support\Carbon;

class MarkAllOpsNotificationsReadAction
{
    /**
     * Marque lues les notifications non lues de l'utilisateur (jusqu'à
     * `$before` si fourni : celles reçues depuis restent non lues).
     *
     * @return int Nombre de notifications marquées lues.
     */
    public function execute(User $user, ?Carbon $before = null): int
    {
        $query = OpsNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at');

        if ($before !== null) {
            $query->where('created_at', '<=', $before);
        }

        return $query->update(['read_at' => now()]);
    }
}
