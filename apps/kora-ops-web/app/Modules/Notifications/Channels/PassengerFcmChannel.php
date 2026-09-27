<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPassengerPushJob;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;

class PassengerFcmChannel
{
    public function send(User $notifiable, PassengerInboxNotification $notification): void
    {
        $row = $notifiable->notifications()
            ->where('audience', 'passenger')
            ->where('dedupe_key', $notification->dedupeKey)
            ->first();

        if ($row === null || ! $notifiable->preference?->push_notifications) {
            return;
        }

        SendPassengerPushJob::dispatch($notifiable->getKey(), (string) $row->getKey())->afterCommit();
    }
}
