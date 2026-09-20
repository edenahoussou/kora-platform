<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un événement métier = une ligne, dédupliquée par `dedupe_key` (un
        // retry retombe sur la même ligne). Ne contient jamais de donnée
        // financière : `data` est construit par OpsNotifier (liste blanche).
        Schema::create('ops_notification_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('dedupe_key', 191)->unique();
            $table->string('type', 48)->index();
            $table->string('severity', 16);
            $table->string('title');
            $table->text('message');
            $table->foreignId('departure_id')->nullable()->constrained('departures')->nullOnDelete();
            $table->foreignId('terminal_p501_id')->nullable()->constrained('terminals_p501')->nullOnDelete();
            // Convoyeur à l'origine de l'alerte : détermine l'agence dont les
            // directeurs des opérations sont destinataires de l'e-mail.
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['departure_id', 'occurred_at']);
        });

        // Une ligne par destinataire : état lu / non lu.
        Schema::create('ops_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ops_notification_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Audit de livraison : événement, destinataire, canal, résultat.
        Schema::create('ops_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ops_notification_events')->cascadeOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('ops_notifications')->nullOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16);
            $table->string('mail_role', 8)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'recipient_user_id', 'channel']);
            $table->index(['status', 'channel']);
        });

        Schema::create('ops_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 48);
            $table->boolean('in_app')->default(true);
            $table->boolean('mail')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_notification_preferences');
        Schema::dropIfExists('ops_notification_deliveries');
        Schema::dropIfExists('ops_notifications');
        Schema::dropIfExists('ops_notification_events');
    }
};
