<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->restrictOnDelete();
            $table->foreignId('terminal_p501_id')->constrained('terminals_p501')->restrictOnDelete();
            $table->foreignId('conductor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 32)->nullable();

            // Dénormalisé depuis la dernière position acceptée : évite une
            // jointure pour l'endpoint fleet/tracking (lu très fréquemment).
            $table->foreignId('last_position_id')->nullable();
            $table->timestamp('last_position_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_accuracy_meters', 8, 2)->nullable();
            $table->decimal('last_speed_kmh', 6, 2)->nullable();
            $table->decimal('last_heading_degrees', 5, 2)->nullable();
            $table->string('last_quality', 16)->nullable();
            $table->string('last_status', 16)->nullable(); // fix | gps_unavailable
            $table->unsignedBigInteger('last_sequence')->nullable();

            $table->timestamps();

            // Un seul index (pas de contrainte unique) : "une seule session
            // ouverte par départ" est garanti au niveau applicatif (voir
            // OpenTrackingSessionAction / OpsTrackingController::resolveSession)
            // plutôt qu'en base — une contrainte unique sur (departure_id,
            // status) interdirait à tort une deuxième session CLOSE sur le
            // même départ (ex. terminal réassigné en cours de route), ce qui
            // arrive légitimement.
            $table->index(['departure_id', 'status']);
            $table->index(['terminal_p501_id', 'status']);
            $table->index(['bus_id', 'status']);
        });

        Schema::create('tracking_positions', function (Blueprint $table): void {
            $table->id();
            // Fourni par le terminal (idempotence) — pas auto-généré.
            $table->uuid('uuid')->unique();
            $table->foreignId('tracking_session_id')->constrained('tracking_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('status', 16)->default('fix'); // fix | gps_unavailable
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('heading_degrees', 5, 2)->nullable();
            $table->string('source', 16)->default('gps'); // gps | network | fused | unknown
            $table->string('quality', 16)->default('accurate'); // accurate | degraded
            $table->timestamp('recorded_at'); // heure locale du terminal
            $table->timestamp('server_received_at');
            $table->timestamps();

            $table->index(['tracking_session_id', 'sequence']);
            $table->index(['tracking_session_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_positions');
        Schema::dropIfExists('tracking_sessions');
    }
};
