<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Notifications\IdentityAccess\KoraPointWebAccessNotification;
use Illuminate\Support\Facades\Notification;

class KoraPointWebAccessDelivery
{
    public function __construct(private readonly ConexteoSmsClient $sms) {}

    public function sendEmail(string $email, KoraPointWebAccessNotification $notification): void
    {
        Notification::route('mail', $email)->notifyNow($notification);
    }

    public function canSendSms(?string $phone): bool
    {
        return filled($phone) && $this->sms->isConfigured();
    }

    public function sendSms(string $phone, KoraPointWebAccessNotification $notification, string $invitationUuid): void
    {
        $this->sms->send(
            $phone,
            $notification->toSms(),
            hash('sha256', 'kora-point-web-access:'.$invitationUuid),
        );
    }
}
