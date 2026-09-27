<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SendOpsInboxPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $userId, public readonly string $notificationUuid) {}

    public function handle(FirebaseMessagingClient $firebase): void
    {
        $user = User::query()->with('devices')->find($this->userId);
        $notification = $user?->notifications()->whereKey($this->notificationUuid)->where('audience', 'ops')->first();
        if ($user === null || $notification === null || $notification->event_id !== null) {
            return;
        }

        $data = $notification->data;
        $summary = match ($data['type'] ?? '') {
            'fiscal_credit_note' => 'Une facture d’avoir a été mise à jour. Ouvrez KORA OPS pour consulter les détails.',
            default => 'Une notification opérationnelle nécessite votre attention.',
        };

        foreach ($user->devices->filter(fn ($device): bool => $device->platform === DevicePlatform::Web && $device->status === UserDeviceStatus::Active && filled($device->push_token)) as $device) {
            $endpoint = 'device:'.$device->uuid;
            $existing = DB::table('notification_channel_deliveries')->where('notification_uuid', $this->notificationUuid)->where('channel', 'push')->where('endpoint_key', $endpoint)->first();
            if ($existing?->status === 'sent') {
                continue;
            }

            try {
                $sent = $firebase->isServerConfigured() && $firebase->send($device->push_token, [
                    'title' => 'Facture d’avoir mise à jour',
                    'body' => $summary,
                    'link' => route('fiscal-invoices.index'),
                ]);
                if ($firebase->isServerConfigured() && ! $sent) {
                    $device->forceFill(['push_token' => null])->save();
                }
                $this->record($endpoint, $sent ? 'sent' : 'skipped', $sent ? null : 'firebase_unavailable_or_invalid_token', $sent, $existing);
            } catch (Throwable $exception) {
                $this->record($endpoint, 'failed', Str::limit($exception->getMessage(), 250, ''), false, $existing);
                throw $exception;
            }
        }
    }

    private function record(string $endpoint, string $status, ?string $error, bool $delivered, ?object $existing): void
    {
        DB::table('notification_channel_deliveries')->updateOrInsert(
            ['notification_uuid' => $this->notificationUuid, 'channel' => 'push', 'endpoint_key' => $endpoint],
            ['user_id' => $this->userId, 'status' => $status, 'attempts' => ($existing->attempts ?? 0) + 1, 'error' => $error, 'delivered_at' => $delivered ? now() : null, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()],
        );
    }
}
