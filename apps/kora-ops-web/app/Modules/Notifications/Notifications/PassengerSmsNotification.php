<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Notification;

final class PassengerSmsNotification extends Notification
{
    public function __construct(
        public readonly string $content,
        public readonly string $dedupeKey,
        public readonly string $type,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): string
    {
        return $this->content;
    }
}
