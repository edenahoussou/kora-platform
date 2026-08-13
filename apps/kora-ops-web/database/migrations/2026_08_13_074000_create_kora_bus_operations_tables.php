<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('terminal_p501_id')->nullable()->constrained('terminals_p501')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('operation_uuid')->unique();
            $table->string('operation_type', 64)->index();
            $table->timestamp('local_recorded_at');
            $table->timestamp('server_received_at')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->json('payload');
            $table->json('result')->nullable();
            $table->timestamps();
        });

        Schema::create('boarding_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('terminal_p501_id')->nullable()->constrained('terminals_p501')->nullOnDelete();
            $table->uuid('operation_uuid')->unique();
            $table->timestamp('local_recorded_at');
            $table->timestamp('server_recorded_at')->nullable();
            $table->string('status', 32)->index();
            $table->json('conflict_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_release_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('seat_id')->constrained('seats')->restrictOnDelete();
            $table->foreignId('actual_stop_id')->constrained('stops')->restrictOnDelete();
            $table->json('released_segments');
            $table->uuid('operation_uuid')->unique();
            $table->timestamp('local_recorded_at');
            $table->timestamp('server_recorded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('accepted')->index();
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->date('business_date')->index();
            $table->unsignedInteger('opening_float')->default(0);
            $table->unsignedInteger('total_cash')->default(0);
            $table->unsignedInteger('total_mobile_money')->default(0);
            $table->integer('expected_balance')->default(0);
            $table->integer('actual_balance')->nullable();
            $table->integer('variance')->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->text('closing_comment')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'agency_id', 'business_date']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->string('type', 32)->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bus_positions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->constrained('departures')->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 6, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->nullable()->constrained('departures')->nullOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained('buses')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->text('description');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('channel', 32)->index();
            $table->string('recipient');
            $table->string('template');
            $table->nullableMorphs('reference');
            $table->string('status', 32)->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('bus_positions');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('seat_release_events');
        Schema::dropIfExists('boarding_events');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('sync_batches');
    }
};
