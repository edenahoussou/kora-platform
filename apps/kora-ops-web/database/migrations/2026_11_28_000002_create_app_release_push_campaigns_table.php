<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #342 — campagnes FCM par topic pour une release store.
 *
 * Une campagne est liée à une seule release (une release = une plateforme).
 * Le topic est dérivé de `app × platform × environment`. Le statut incertain
 * reste bloqué tant qu'une décision Ops explicite n'a pas été enregistrée
 * avant relance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_release_push_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('release_id')->constrained('app_releases')->cascadeOnDelete();
            $table->string('app', 32);
            $table->string('platform', 10);
            $table->string('environment', 20);
            $table->string('topic', 255);
            $table->string('status', 20)->default('pending');
            $table->string('title', 255);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('fcm_message_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retry_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retry_decision_at')->nullable();
            $table->text('retry_decision_note')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique('release_id');
            $table->index(['app', 'platform', 'environment', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_release_push_campaigns');
    }
};
