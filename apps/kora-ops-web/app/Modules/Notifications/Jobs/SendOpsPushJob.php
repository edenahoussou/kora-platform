<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class SendOpsPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $eventId,
        public readonly int $deviceId,
        public readonly string $endpointKey,
        public readonly ?string $notificationUuid = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(FirebaseMessagingClient $fcm): void
    {
        $event = OpsNotificationEvent::query()->find($this->eventId);
        $device = UserDevice::query()->whereKey($this->deviceId)->where('status', UserDeviceStatus::Active->value)->first();

        if ($event === null || $device === null || $device->push_token === null) {
            return;
        }

        $delivery = OpsNotificationDelivery::query()->firstOrNew([
            'event_id' => $event->id,
            'recipient_user_id' => $device->user_id,
            'channel' => OpsNotificationChannel::Push->value,
            'endpoint_key' => $this->endpointKey,
        ]);

        if (! $fcm->isServerConfigured()) {
            $delivery->forceFill(['status' => OpsNotificationDeliveryStatus::Skipped, 'error' => 'firebase_not_configured'])->save();

            return;
        }

        $body = $this->safeSummary($event->type->value);
        try {
            $message = [
                'title' => $event->title,
                'body' => $body,
                // Charge utile minimale pour le deep-link mobile : codes et UUID uniquement.
                'data' => array_filter([
                    'type' => $event->type->value,
                    'event_id' => (string) $event->id,
                    'notification_uuid' => $this->notificationUuid,
                    'action' => $event->type->action()->value,
                    'severity' => $event->type->severity()->value,
                    'departure_uuid' => $event->departure?->uuid,
                ]),
            ];

            if ($device->platform === DevicePlatform::Web) {
                $assistedBookingUuid = $event->data['assisted_booking_uuid'] ?? null;
                $message['link'] = is_string($assistedBookingUuid)
                    ? route('assisted-bookings.index', ['open' => $assistedBookingUuid])
                    : route('dashboard');
            } else {
                $message['platform'] = $device->platform->value;
            }

            $sent = $fcm->send($device->push_token, $message);

            if (! $sent) {
                $device->forceFill(['push_token' => null])->save();
            }

            $delivery->forceFill([
                'notification_uuid' => $this->notificationUuid,
                'status' => $sent ? OpsNotificationDeliveryStatus::Sent : OpsNotificationDeliveryStatus::Skipped,
                'attempts' => $delivery->attempts + 1,
                'error' => $sent ? null : 'invalid_registration_token',
                'delivered_at' => $sent ? now() : null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'notification_uuid' => $this->notificationUuid,
                'status' => OpsNotificationDeliveryStatus::Failed,
                'attempts' => $delivery->attempts + 1,
                'error' => Str::limit($exception->getMessage(), 250, ''),
                'delivered_at' => null,
            ])->save();

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $device = UserDevice::query()->find($this->deviceId);
        if ($device === null) {
            return;
        }

        OpsNotificationDelivery::query()
            ->where('event_id', $this->eventId)
            ->where('recipient_user_id', $device->user_id)
            ->where('channel', OpsNotificationChannel::Push->value)
            ->where('endpoint_key', $this->endpointKey)
            ->update(['status' => OpsNotificationDeliveryStatus::Failed->value, 'error' => Str::limit($exception->getMessage(), 250, '')]);
    }

    private function safeSummary(string $type): string
    {
        return match ($type) {
            'fiscalization_failed' => 'Une opération de fiscalisation a échoué après ses dernières tentatives.',
            'cash_variance_submitted' => 'Une clôture de caisse avec écart attend votre approbation.',
            OpsNotificationType::AssistedBookingRequested->value => OpsNotificationType::ASSISTED_BOOKING_REQUEST_MESSAGE,
            'assisted_booking_reassigned' => 'Une demande d’assistance vous a été réaffectée.',
            'departure_delayed' => 'Un départ de votre périmètre a été retardé.',
            default => 'Une alerte opérationnelle nécessite votre attention.',
        };
    }
}
