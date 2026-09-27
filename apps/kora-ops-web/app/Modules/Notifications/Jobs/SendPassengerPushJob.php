<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SendPassengerPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $userId, public readonly string $notificationUuid) {}

    public function handle(FirebaseMessagingClient $firebase): void
    {
        $user = User::query()->with(['preference', 'devices'])->find($this->userId);
        $notification = $user?->notifications()->whereKey($this->notificationUuid)->first();
        if ($user === null || $notification === null || ($notification->data['audience'] ?? null) !== 'passenger' || ! $user->preference?->push_notifications) {
            return;
        }

        $configured = $firebase->isServerConfigured();
        foreach ($user->devices->filter(fn ($device): bool => $device->status->value === 'active' && filled($device->push_token)) as $device) {
            if (! in_array($device->platform, [DevicePlatform::Android, DevicePlatform::Ios], true)) {
                continue;
            }
            $endpoint = 'device:'.$device->uuid;
            $existing = DB::table('notification_channel_deliveries')
                ->where('notification_uuid', $this->notificationUuid)->where('channel', 'push')->where('endpoint_key', $endpoint)->first();
            if ($existing !== null && $existing->status === 'sent') {
                continue;
            }

            try {
                $sent = $configured && $firebase->send($device->push_token, [
                    'title' => (string) ($notification->data['title'] ?? 'KORA BUS'),
                    'body' => (string) ($notification->data['message'] ?? 'Une mise à jour de votre trajet est disponible.'),
                    'link' => '/tickets',
                ]);
                if ($configured && ! $sent) {
                    $device->forceFill(['push_token' => null])->save();
                }
                $this->record($endpoint, $sent ? 'sent' : ($configured ? 'skipped' : 'skipped'), $sent ? null : ($configured ? 'invalid_registration_token' : 'firebase_not_configured'), $sent);
            } catch (Throwable $exception) {
                $this->record($endpoint, 'failed', Str::limit($exception->getMessage(), 250, ''), false);
                throw $exception;
            }
        }
    }

    private function record(string $endpoint, string $status, ?string $error, bool $delivered): void
    {
        $query = DB::table('notification_channel_deliveries');
        $existing = $query->where('notification_uuid', $this->notificationUuid)
            ->where('channel', 'push')->where('endpoint_key', $endpoint)->first();
        $query->updateOrInsert(
            ['notification_uuid' => $this->notificationUuid, 'channel' => 'push', 'endpoint_key' => $endpoint],
            ['user_id' => $this->userId, 'status' => $status, 'attempts' => ($existing->attempts ?? 0) + 1, 'error' => $error, 'delivered_at' => $delivered ? now() : null, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()],
        );
    }
}
