<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\SendPointsExpiryRemindersAction;
use Illuminate\Console\Command;

/**
 * Envoie les rappels J-x avant expiration des points.
 */
class ExpiryRemindersCommand extends Command
{
    protected $signature = 'wallet:expiry-reminders';

    protected $description = 'Envoie les rappels J-x avant expiration des points';

    /**
     * Exécute l'action de rappel et affiche le bilan.
     *
     * @param  SendPointsExpiryRemindersAction  $sendReminders  Action de rappel
     * @return int Code de sortie de la commande
     */
    public function handle(SendPointsExpiryRemindersAction $sendReminders): int
    {
        $count = $sendReminders->handle();
        $this->info("{$count} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
