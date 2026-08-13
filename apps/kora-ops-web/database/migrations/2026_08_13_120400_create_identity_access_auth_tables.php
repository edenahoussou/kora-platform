<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 32)->index();
            $table->string('code_hash');
            $table->string('verification_token_hash')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose', 'status']);
        });

        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 32)->index();
            $table->string('device_fingerprint_hash', 64);
            $table->string('push_token')->nullable();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('auth_otp_challenges');
    }
};
