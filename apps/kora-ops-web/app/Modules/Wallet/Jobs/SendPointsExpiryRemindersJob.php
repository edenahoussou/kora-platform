<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\SendPointsExpiryRemindersAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPointsExpiryRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(SendPointsExpiryRemindersAction $sendReminders): void
    {
        $sendReminders->handle();
    }
}
