<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Notification;

class OpsMailDispatchNotification extends Notification
{
    public function __construct(public readonly int $eventId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['opsMail'];
    }
}
