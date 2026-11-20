<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_incidents', function (Blueprint $table): void {
            $table->foreignId('opened_by_id')->nullable()->after('parcel_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->after('resolved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable()->after('resolved_by_id');
            $table->string('idempotency_key', 64)->nullable()->after('blocking');
            $table->unique(['parcel_id', 'idempotency_key'], 'parcel_incidents_parcel_idempotency_unique');
        });

        Schema::table('parcel_outboxes', function (Blueprint $table): void {
            $table->timestampTz('claimed_at')->nullable()->after('completed_at');
            $table->uuid('claim_token')->nullable()->after('claimed_at');
            $table->string('last_error_code', 64)->nullable()->after('claim_token');
            $table->string('delivered_channel', 24)->nullable()->after('last_error_code');
            $table->string('provider_message_id', 191)->nullable()->after('delivered_channel');
            $table->index('claimed_at', 'parcel_outboxes_claimed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_outboxes', function (Blueprint $table): void {
            $table->dropIndex('parcel_outboxes_claimed_at_index');
            $table->dropColumn(['claimed_at', 'claim_token', 'last_error_code', 'delivered_channel', 'provider_message_id']);
        });

        Schema::table('parcel_incidents', function (Blueprint $table): void {
            $table->dropUnique('parcel_incidents_parcel_idempotency_unique');
            $table->dropConstrainedForeignId('opened_by_id');
            $table->dropConstrainedForeignId('resolved_by_id');
            $table->dropColumn(['resolution_reason', 'idempotency_key']);
        });
    }
};
