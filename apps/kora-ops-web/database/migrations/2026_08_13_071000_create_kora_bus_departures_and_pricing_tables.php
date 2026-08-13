<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('line_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->restrictOnDelete();
            $table->date('departure_date')->index();
            $table->time('departure_time');
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('scheduled')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['line_id', 'departure_date', 'status']);
        });

        Schema::create('fares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('line_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('origin_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('destination_stop_id')->constrained('stops')->restrictOnDelete();
            $table->unsignedInteger('base_amount');
            $table->string('currency', 3)->default('XOF');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['line_id', 'origin_stop_id', 'destination_stop_id', 'status']);
        });

        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('line_id')->nullable()->constrained('lines')->cascadeOnDelete();
            $table->string('type', 32);
            $table->json('conditions');
            $table->decimal('percentage', 7, 4);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['line_id', 'status', 'priority']);
        });

        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('service_type', 64);
            $table->string('scope', 32);
            $table->string('tax_code', 64);
            $table->decimal('tax_rate', 7, 4);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_type', 'scope', 'status']);
        });

        Schema::create('price_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->constrained('departures')->cascadeOnDelete();
            $table->foreignId('origin_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('destination_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('fare_id')->nullable()->constrained('fares')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->unsignedInteger('base_amount');
            $table->decimal('coefficient', 8, 4)->default(1);
            $table->unsignedInteger('final_amount');
            $table->string('currency', 3)->default('XOF');
            $table->json('snapshot');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('fares');
        Schema::dropIfExists('departures');
    }
};
