<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assisted_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_channel', 32);
            $table->string('handoff_channel', 16);
            $table->string('status', 32)->default('pending');
            $table->string('contact_name', 120);
            $table->string('contact_phone', 32);
            $table->json('snapshot');
            $table->unsignedInteger('original_total_amount');
            $table->unsignedInteger('current_total_amount')->nullable();
            $table->string('currency', 3);
            $table->string('idempotency_key', 100)->unique();
            $table->string('resume_idempotency_key', 100)->nullable()->unique();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_note')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('refused_at')->nullable();
            $table->timestampTz('unavailable_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['preferred_agency_id', 'status', 'created_at'], 'assisted_queue_agency_status_idx');
            $table->index(['assigned_user_id', 'status'], 'assisted_queue_assignee_status_idx');
            $table->index(['status', 'expires_at'], 'assisted_queue_expiry_idx');
            $table->index('contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assisted_bookings');
    }
};
