<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use Illuminate\Notifications\DatabaseNotification;

class PassengerDatabaseChannel
{
    public function send(User $notifiable, PassengerInboxNotification $notification): DatabaseNotification
    {
        $payload = $notification->toArray();
        $existing = $notifiable->notifications()
            ->where('audience', 'passenger')
            ->where('dedupe_key', $notification->dedupeKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $notifiable->notifications()->create([
            'id' => $notification->id,
            'type' => $notification::class,
            'audience' => 'passenger',
            'user_id' => $notifiable->getKey(),
            'dedupe_key' => $notification->dedupeKey,
            'data' => $payload,
        ]);
    }
}
