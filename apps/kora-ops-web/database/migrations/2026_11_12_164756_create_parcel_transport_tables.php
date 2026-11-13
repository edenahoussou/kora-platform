<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_hubs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unique(['agency_id', 'stop_id']);
            $table->timestamps();
        });

        Schema::create('parcel_corridors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('origin_hub_id')->constrained('parcel_hubs')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('parcel_hubs')->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('wallet_cash_enabled')->default(false);
            $table->unique(['origin_hub_id', 'destination_hub_id']);
            $table->timestamps();
        });

        Schema::create('parcel_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('corridor_id')->constrained('parcel_corridors')->restrictOnDelete();
            $table->unsignedInteger('base_amount');
            $table->unsignedInteger('step_amount')->default(1500);
            $table->unsignedInteger('step_grams')->default(5000);
            $table->unsignedInteger('maximum_grams')->default(30000);
            $table->unsignedInteger('return_amount');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('parcels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 17)->nullable()->unique();
            $table->foreignId('corridor_id')->constrained('parcel_corridors')->restrictOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('custody_hub_id')->nullable()->constrained('parcel_hubs')->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->text('sender_name');
            $table->text('sender_phone');
            $table->text('recipient_name');
            $table->text('recipient_phone');
            $table->string('recipient_phone_hash', 64)->index();
            $table->text('contents');
            $table->unsignedInteger('declared_grams');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('return_due_at')->nullable()->index();
            $table->timestampTz('return_handed_over_at')->nullable();
            $table->string('handoff_hash')->nullable();
            $table->unsignedSmallInteger('handoff_attempts')->default(0);
            $table->string('share_hash', 64)->nullable()->unique();
            $table->timestampsTz();
        });

        Schema::create('parcel_soute_budgets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('bus_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('capacity_grams');
            $table->timestampsTz();
        });

        Schema::create('parcel_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('departure_id')->constrained()->restrictOnDelete();
            $table->foreignId('bus_id')->constrained()->restrictOnDelete();
            $table->boolean('return_leg')->default(false);
            $table->timestampTz('loaded_at')->nullable();
            $table->timestampTz('transported_at')->nullable();
            $table->timestampTz('unloaded_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->index(['departure_id', 'released_at']);
            $table->index(['parcel_id', 'released_at']);
            $table->timestampsTz();
        });

        Schema::create('parcel_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('tariff_id')->constrained('parcel_tariffs')->restrictOnDelete();
            $table->foreignId('tax_rule_id')->constrained()->restrictOnDelete();
            $table->boolean('return_leg')->default(false);
            $table->unsignedInteger('total');
            $table->unsignedInteger('net');
            $table->unsignedInteger('tax');
            $table->string('tax_code');
            $table->decimal('tax_rate', 7, 4);
            $table->string('fiscal_group', 1);
            $table->foreignId('settled_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->timestampTz('superseded_at')->nullable();
            $table->index(['parcel_id', 'return_leg', 'superseded_at']);
            $table->timestampsTz();
        });

        Schema::create('parcel_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals_p501')->restrictOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained('parcel_hubs')->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('parcel_assignments')->restrictOnDelete();
            $table->uuid('operation_uuid')->nullable()->unique();
            $table->string('kind', 40);
            $table->string('channel', 24);
            $table->timestampTz('observed_at');
            $table->json('details')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parcel_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->text('description');
            $table->boolean('blocking');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parcel_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parcel_id')->constrained()->restrictOnDelete();
            $table->string('key')->unique();
            $table->string('kind', 40);
            $table->text('payload');
            $table->timestampTz('due_at')->index();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['parcel_outboxes', 'parcel_incidents', 'parcel_events', 'parcel_charges', 'parcel_assignments', 'parcel_soute_budgets', 'parcels', 'parcel_tariffs', 'parcel_corridors', 'parcel_hubs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
