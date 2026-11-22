<?php

namespace App\Modules\IdentityAccess\Services;

use App\Notifications\IdentityAccess\KoraPointWebAccessNotification;
use Illuminate\Support\Facades\Notification;

class KoraPointWebAccessDelivery
{
    public function sendEmail(string $email, KoraPointWebAccessNotification $notification): void
    {
        Notification::route('mail', $email)->notifyNow($notification);
    }
}
