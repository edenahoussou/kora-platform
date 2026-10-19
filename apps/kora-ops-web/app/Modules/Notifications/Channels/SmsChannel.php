<?php

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Notifications\Notifications\PassengerSmsNotification;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;

class SmsChannel
{
    public function __construct(private readonly ConexteoSmsClient $client) {}

    public function send(object $notifiable, PassengerSmsNotification $notification): void
    {
        if (! $this->client->isConfigured()) {
            return;
        }

        $recipient = $this->recipient($notifiable);

        if ($recipient === null) {
            return;
        }

        SendSmsJob::dispatch(
            recipient: $recipient,
            content: $notification->content,
            dedupeKey: $notification->dedupeKey,
            type: $notification->type,
            referenceType: $notification->referenceType,
            referenceId: $notification->referenceId,
        )->afterCommit();
    }

    private function recipient(object $notifiable): ?string
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sms')
            : null;

        $phone = is_string($route) ? $route : ($notifiable->phone ?? null);

        return is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
    }
}
