<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('city');
            $table->string('country', 2)->default('BJ');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_uuid')->unique();
            $table->string('type', 32);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('terminals_p501', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('serial_number')->unique();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('countries_crossed')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stops', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('line_id')->constrained('lines')->cascadeOnDelete();
            $table->string('name');
            $table->string('city');
            $table->string('country', 2)->default('BJ');
            $table->unsignedInteger('stop_order');
            $table->decimal('km_from_origin', 8, 2)->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['line_id', 'stop_order']);
        });

        Schema::create('buses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('registration_number')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('seat_count')->default(55);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('seats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('type', 32)->default('standard');
            $table->boolean('sellable')->default(true)->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bus_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('buses');
        Schema::dropIfExists('stops');
        Schema::dropIfExists('lines');
        Schema::dropIfExists('terminals_p501');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('agencies');
    }
};
