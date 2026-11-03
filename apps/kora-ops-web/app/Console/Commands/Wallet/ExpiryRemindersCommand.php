<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\SendPointsExpiryRemindersAction;
use Illuminate\Console\Command;

class ExpiryRemindersCommand extends Command
{
    protected $signature = 'wallet:expiry-reminders';

    protected $description = 'Envoie les rappels J-x avant expiration des points';

    public function handle(SendPointsExpiryRemindersAction $sendReminders): int
    {
        $count = $sendReminders->handle();
        $this->info("{$count} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
