<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendOpsInboxPushJob;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OpsDatabaseChannel
{
    public function send(User $notifiable, Notification $notification): DatabaseNotification
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            $data = call_user_func([$notification, 'toArray'], $notifiable);
        } else {
            throw new \LogicException('OPS inbox notifications must define a database payload.');
        }
        $eventUuid = (string) ($data['event_uuid'] ?? Str::uuid());
        $dedupeKey = 'ops:'.$eventUuid.':'.$notifiable->getKey();
        $existing = $notifiable->notifications()->where('audience', 'ops')->where('dedupe_key', $dedupeKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $eventId = OpsNotificationEvent::query()->where('uuid', $eventUuid)->value('id');

        $record = $notifiable->notifications()->create([
            'id' => $notification->id,
            'type' => $notification::class,
            'data' => $data,
            'audience' => 'ops',
            'event_id' => $eventId,
            'user_id' => $notifiable->getKey(),
            'dedupe_key' => $dedupeKey,
        ]);

        if ($eventId === null) {
            SendOpsInboxPushJob::dispatch($notifiable->getKey(), (string) $record->getKey())->afterCommit();
        }

        return $record;
    }
}
