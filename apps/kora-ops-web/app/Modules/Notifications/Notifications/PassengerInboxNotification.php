<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PassengerInboxNotification extends Notification
{
    /** @param array<string, scalar|null> $data */
    public function __construct(
        public readonly string $dedupeKey,
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $resourceUuid = null,
        public readonly array $data = [],
    ) {
        $this->id = (string) Str::uuid();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['passengerDatabase', 'passengerFcm'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'audience' => 'passenger',
            'dedupe_key' => $this->dedupeKey,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'resource_uuid' => $this->resourceUuid,
            'data' => $this->data,
        ];
    }
}
