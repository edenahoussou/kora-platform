<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\SendPointsExpiryRemindersAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoie les rappels J-x d'expiration des points.
 */
class SendPointsExpiryRemindersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Exécute l'action de rappel.
     *
     * @param  SendPointsExpiryRemindersAction  $sendReminders  Action de rappel
     */
    public function handle(SendPointsExpiryRemindersAction $sendReminders): void
    {
        $sendReminders->handle();
    }
}
