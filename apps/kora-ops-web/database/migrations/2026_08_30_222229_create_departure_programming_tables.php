<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departure_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(0);
            $table->json('draft')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('departure_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->constrained('departure_templates')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->json('slots');
            $table->timestamps();
            $table->unique(['template_id', 'number']);
        });
        Schema::create('departure_programmes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->foreignId('version_id')->constrained('departure_template_versions')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('automatic')->default(false)->index();
            $table->unsignedSmallInteger('horizon_days')->default(30);
            $table->unsignedInteger('revision')->default(1);
            $table->json('exceptions');
            $table->json('version_schedule');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('planned_departures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained('departure_programmes')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('departure_template_versions')->restrictOnDelete();
            $table->uuid('slot_key');
            $table->date('service_date');
            $table->timestampTz('scheduled_at')->index();
            $table->timestampTz('arrival_at');
            $table->string('timezone', 64);
            $table->foreignId('line_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained('buses')->restrictOnDelete();
            foreach (['driver_id', 'secondary_driver_id', 'conductor_id', 'mechanic_id'] as $field) {
                $table->foreignId($field)->nullable()->constrained('users')->restrictOnDelete();
            }
            $table->foreignId('departure_id')->nullable()->unique()->constrained('departures')->restrictOnDelete();
            $table->string('state', 20)->default('planned');
            $table->boolean('is_exception')->default(false);
            $table->json('snapshot');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['programme_id', 'slot_key', 'service_date'], 'planned_departure_occurrence_unique');
            $table->index(['bus_id', 'scheduled_at', 'arrival_at']);
        });
        Schema::create('programming_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('programme_id')->constrained('departure_programmes')->restrictOnDelete();
            $table->string('operation', 24);
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('processed')->default(0);
            $table->json('result');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['programming_runs', 'planned_departures', 'departure_programmes', 'departure_template_versions', 'departure_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
