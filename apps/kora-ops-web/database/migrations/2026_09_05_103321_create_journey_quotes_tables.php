<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trip_type', 16);
            $table->unsignedSmallInteger('passenger_count');
            $table->unsignedInteger('subtotal_amount');
            $table->unsignedInteger('dynamic_adjustment_amount');
            $table->unsignedInteger('group_discount_amount');
            $table->unsignedInteger('total_amount');
            $table->string('currency', 3);
            $table->json('snapshot');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['trip_type', 'expires_at']);
        });

        Schema::create('journey_quote_legs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('journey_quote_id')->constrained('journey_quotes')->cascadeOnDelete();
            $table->string('direction', 8);
            $table->unsignedSmallInteger('leg_order');
            $table->foreignId('price_quote_id')->constrained('price_quotes')->restrictOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('origin_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('destination_stop_id')->constrained('stops')->restrictOnDelete();
            $table->timestamp('departure_at');
            $table->timestamp('arrival_at');
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->unique(['journey_quote_id', 'direction', 'leg_order']);
            $table->index(['journey_quote_id', 'direction']);
            $table->index(['departure_id', 'origin_stop_id', 'destination_stop_id']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('journey_quote_id')
                ->nullable()
                ->after('itinerary_quote_id')
                ->constrained('journey_quotes')
                ->nullOnDelete();
        });

        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->string('direction', 8)->default('outbound')->after('leg_order')->index();
            $table->dropUnique('reservation_segments_passenger_matrix_unique');
            $table->unique(
                ['reservation_id', 'direction', 'passenger_index', 'leg_order'],
                'reservation_segments_direction_passenger_matrix_unique',
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE journey_quotes ADD CONSTRAINT journey_quotes_trip_type_check CHECK (trip_type IN ('one_way', 'round_trip'))");
            DB::statement("ALTER TABLE journey_quote_legs ADD CONSTRAINT journey_quote_legs_direction_check CHECK (direction IN ('outbound', 'inbound'))");
            DB::statement("ALTER TABLE reservation_segments ADD CONSTRAINT reservation_segments_direction_check CHECK (direction IN ('outbound', 'inbound'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservation_segments DROP CONSTRAINT IF EXISTS reservation_segments_direction_check');
            DB::statement('ALTER TABLE journey_quote_legs DROP CONSTRAINT IF EXISTS journey_quote_legs_direction_check');
            DB::statement('ALTER TABLE journey_quotes DROP CONSTRAINT IF EXISTS journey_quotes_trip_type_check');
        }

        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->dropUnique('reservation_segments_direction_passenger_matrix_unique');
            $table->dropIndex(['direction']);
            $table->dropColumn('direction');
            $table->unique(
                ['reservation_id', 'passenger_index', 'leg_order'],
                'reservation_segments_passenger_matrix_unique',
            );
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['journey_quote_id']);
            $table->dropColumn('journey_quote_id');
        });

        Schema::dropIfExists('journey_quote_legs');
        Schema::dropIfExists('journey_quotes');
    }
};
