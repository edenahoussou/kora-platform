<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('audience', 24)->nullable()->index();
            $table->foreignId('event_id')->nullable()->constrained('ops_notification_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('dedupe_key', 190)->nullable();
            $table->unique(['event_id', 'user_id']);
            $table->unique(['notifiable_type', 'notifiable_id', 'dedupe_key'], 'notifications_recipient_dedupe_unique');
            $table->index(['user_id', 'audience', 'read_at', 'created_at'], 'notifications_user_audience_read_created_index');
        });

        Schema::table('ops_notification_deliveries', function (Blueprint $table): void {
            $table->string('notification_uuid', 36)->nullable()->after('event_id');
            $table->string('endpoint_key', 64)->default('user')->after('channel');
        });

        Schema::create('notification_channel_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_uuid');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('endpoint_key', 64);
            $table->string('status', 24);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 250)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_uuid', 'channel', 'endpoint_key'], 'notification_channel_endpoint_unique');
        });

        DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNull('audience')
            ->orderBy('id')
            ->chunkById(200, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $payload = is_string($notification->data)
                        ? json_decode($notification->data, true)
                        : (array) $notification->data;
                    if (! is_array($payload) || (($payload['type'] ?? $payload['kind'] ?? null) !== 'fiscal_credit_note')) {
                        continue;
                    }

                    DB::table('notifications')->where('id', $notification->id)->update([
                        'audience' => 'ops',
                        'user_id' => (int) $notification->notifiable_id,
                    ]);
                }
            });

        DB::table('notifications')
            ->where('audience', 'ops')
            ->whereNull('user_id')
            ->where('notifiable_type', 'App\\Models\\User')
            ->orderBy('id')
            ->chunk(200, function ($notifications): void {
                foreach ($notifications as $notification) {
                    DB::table('notifications')->where('id', $notification->id)->update([
                        'user_id' => (int) $notification->notifiable_id,
                    ]);
                }
            });

        DB::table('ops_notifications')->orderBy('id')->chunk(200, function ($notifications): void {
            foreach ($notifications as $opsNotification) {
                $event = DB::table('ops_notification_events')->where('id', $opsNotification->event_id)->first();
                if ($event === null) {
                    continue;
                }

                $data = [
                    'audience' => 'ops',
                    'event_uuid' => $event->uuid,
                    'type' => $event->type,
                    'severity' => $event->severity,
                    'title' => $event->title,
                    'message' => $event->message,
                    'occurred_at' => $event->occurred_at,
                    'data' => $event->data === null ? [] : json_decode($event->data, true),
                ];

                DB::table('notifications')->updateOrInsert(
                    ['id' => $opsNotification->uuid],
                    [
                        'type' => 'ops',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => (string) $opsNotification->user_id,
                        'event_id' => $opsNotification->event_id,
                        'user_id' => $opsNotification->user_id,
                        'audience' => 'ops',
                        'dedupe_key' => 'ops:'.$event->uuid.':'.$opsNotification->user_id,
                        'data' => json_encode($data, JSON_THROW_ON_ERROR),
                        'read_at' => $opsNotification->read_at,
                        'created_at' => $opsNotification->created_at,
                        'updated_at' => $opsNotification->updated_at,
                    ],
                );
            }
        });

        DB::table('ops_notification_deliveries')->orderBy('id')->chunk(200, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                $notificationUuid = DB::table('ops_notifications')->where('id', $delivery->notification_id)->value('uuid');
                DB::table('ops_notification_deliveries')->where('id', $delivery->id)->update([
                    'notification_uuid' => $notificationUuid,
                ]);
            }
        });

        Schema::table('ops_notification_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['notification_id']);
            $table->dropUnique(['event_id', 'recipient_user_id', 'channel']);
            $table->dropColumn('notification_id');
            $table->unique(['event_id', 'recipient_user_id', 'channel', 'endpoint_key'], 'ops_notification_deliveries_endpoint_unique');
        });

        Schema::drop('ops_notifications');
    }

    public function down(): void
    {
        Schema::create('ops_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ops_notification_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        DB::table('notifications')->where('audience', 'ops')->whereNotNull('event_id')->orderBy('id')->chunk(200, function ($notifications): void {
            foreach ($notifications as $notification) {
                DB::table('ops_notifications')->insert([
                    'uuid' => $notification->id,
                    'event_id' => $notification->event_id,
                    'user_id' => $notification->user_id,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ]);
            }
        });

        Schema::table('ops_notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('ops_notification_deliveries_endpoint_unique');
            $table->dropColumn('endpoint_key');
            $table->foreignId('notification_id')->nullable()->after('event_id')->constrained('ops_notifications')->nullOnDelete();
        });

        DB::table('ops_notification_deliveries')->orderBy('id')->chunk(200, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                $notificationId = DB::table('ops_notifications')->where('uuid', $delivery->notification_uuid)->value('id');
                DB::table('ops_notification_deliveries')->where('id', $delivery->id)->update([
                    'notification_id' => $notificationId,
                ]);
            }
        });

        DB::table('ops_notification_deliveries')
            ->whereNotIn('id', DB::table('ops_notification_deliveries')
                ->selectRaw('MIN(id)')
                ->groupBy(['event_id', 'recipient_user_id', 'channel']))
            ->delete();

        Schema::table('ops_notification_deliveries', function (Blueprint $table): void {
            $table->unique(['event_id', 'recipient_user_id', 'channel']);
        });

        Schema::table('ops_notification_deliveries', function (Blueprint $table): void {
            $table->dropColumn('notification_uuid');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['event_id', 'user_id']);
            $table->dropUnique('notifications_recipient_dedupe_unique');
            $table->dropIndex('notifications_user_audience_read_created_index');
            $table->dropColumn(['audience', 'event_id', 'user_id', 'dedupe_key']);
        });

        Schema::dropIfExists('notification_channel_deliveries');
    }
};
