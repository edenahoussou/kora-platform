<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('price_quote_id')->nullable()->constrained('price_quotes')->nullOnDelete();
            $table->string('status', 32)->default('held')->index();
            $table->unsignedInteger('total_amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('channel', 32)->default('online')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('total_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['departure_id', 'status']);
        });

        Schema::create('reservation_passengers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('document_type', 32)->nullable();
            $table->string('document_number')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_segments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('reservation_passenger_id')->nullable()->constrained('reservation_passengers')->nullOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('seat_id')->constrained('seats')->restrictOnDelete();
            $table->foreignId('origin_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('destination_stop_id')->constrained('stops')->restrictOnDelete();
            $table->unsignedInteger('origin_stop_order');
            $table->unsignedInteger('destination_stop_order');
            $table->string('status', 32)->default('held')->index();
            $table->foreignId('actual_dropoff_stop_id')->nullable()->constrained('stops')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['departure_id', 'seat_id', 'status']);
            $table->index(['origin_stop_id', 'destination_stop_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(
                'ALTER TABLE reservation_segments
                ADD CONSTRAINT reservation_segments_valid_stop_order
                CHECK (origin_stop_order < destination_stop_order)'
            );
            DB::statement(
                "ALTER TABLE reservation_segments
                ADD CONSTRAINT reservation_segments_no_overlap
                EXCLUDE USING gist (
                    departure_id WITH =,
                    seat_id WITH =,
                    int4range(origin_stop_order::integer, destination_stop_order::integer, '[)') WITH &&
                )
                WHERE (status IN ('held', 'confirmed', 'paid', 'ticketed', 'boarded'))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservation_segments DROP CONSTRAINT IF EXISTS reservation_segments_no_overlap');
            DB::statement('ALTER TABLE reservation_segments DROP CONSTRAINT IF EXISTS reservation_segments_valid_stop_order');
        }

        Schema::dropIfExists('reservation_segments');
        Schema::dropIfExists('reservation_passengers');
        Schema::dropIfExists('reservations');
    }
};
