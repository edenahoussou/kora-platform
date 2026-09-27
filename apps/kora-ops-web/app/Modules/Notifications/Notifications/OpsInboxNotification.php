<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OpsInboxNotification extends Notification
{
    public function __construct(public readonly OpsNotificationEvent $event)
    {
        $this->id = (string) Str::uuid();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['opsDatabase'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $this->event->loadMissing(['departure.line', 'terminal']);

        return [
            'audience' => 'ops',
            'event_uuid' => $this->event->uuid,
            'type' => $this->event->type->value,
            'severity' => $this->event->severity->value,
            'title' => $this->event->title,
            'message' => $this->event->message,
            'action' => $this->event->type->action()->value,
            'occurred_at' => $this->event->occurred_at->toISOString(),
            'departure' => $this->event->departure === null ? null : [
                'uuid' => $this->event->departure->uuid,
                'line_code' => $this->event->departure->line?->code,
                'date' => $this->event->departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $this->event->departure->departure_time, 0, 5),
            ],
            'terminal' => $this->event->terminal === null ? null : [
                'uuid' => $this->event->terminal->uuid,
                'serial_number' => $this->event->terminal->serial_number,
            ],
            'data' => $this->event->data ?? [],
        ];
    }
}
