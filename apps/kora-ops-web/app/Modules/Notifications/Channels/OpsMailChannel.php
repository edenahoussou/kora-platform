<?php

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Jobs\SendOpsAlertMailJob;
use App\Modules\Notifications\Notifications\OpsMailDispatchNotification;
use Illuminate\Notifications\Notification;

class OpsMailChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if ($notification instanceof OpsMailDispatchNotification) {
            SendOpsAlertMailJob::dispatch($notification->eventId)->afterCommit();
        }
    }
}
