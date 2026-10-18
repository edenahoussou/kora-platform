<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Jobs\SendOpsPushJob;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Notifications\OpsPushNotification;
use Illuminate\Notifications\Notification;

class OpsFirebaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof OpsPushNotification) {
            return;
        }

        $event = OpsNotificationEvent::query()->find($notification->eventId);
        if ($event === null) {
            return;
        }

        UserDevice::query()
            ->where('user_id', $notifiable->id)
            ->whereIn('platform', [
                DevicePlatform::Web->value,
                DevicePlatform::Android->value,
                DevicePlatform::Ios->value,
            ])
            ->where('status', UserDeviceStatus::Active->value)
            ->whereNotNull('push_token')
            ->each(function (UserDevice $device) use ($notification, $notifiable): void {
                $endpointKey = 'device:'.$device->uuid;
                $delivery = OpsNotificationDelivery::query()->firstOrCreate(
                    [
                        'event_id' => $notification->eventId,
                        'recipient_user_id' => $notifiable->id,
                        'channel' => OpsNotificationChannel::Push->value,
                        'endpoint_key' => $endpointKey,
                    ],
                    ['status' => OpsNotificationDeliveryStatus::Pending, 'attempts' => 0],
                );

                if ($delivery->status === OpsNotificationDeliveryStatus::Sent) {
                    return;
                }

                $inboxNotification = OpsNotification::query()
                    ->where('event_id', $notification->eventId)
                    ->where('user_id', $notifiable->id)
                    ->first();

                SendOpsPushJob::dispatch(
                    $notification->eventId,
                    $device->id,
                    $endpointKey,
                    $inboxNotification?->uuid,
                )->afterCommit();
            });
    }
}
