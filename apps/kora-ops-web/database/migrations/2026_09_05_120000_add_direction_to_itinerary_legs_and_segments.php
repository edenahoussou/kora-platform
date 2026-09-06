<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allows one itinerary quote / reservation to carry both directions of a
 * round trip (outbound + inbound legs) instead of a single one-way journey.
 *
 * `leg_order` stays globally unique per quote/reservation (outbound legs
 * numbered first, inbound legs continue the sequence), so no existing
 * unique constraint needs to change — `direction` is purely a display /
 * grouping tag on top of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itinerary_quote_legs', function (Blueprint $table): void {
            $table->string('direction', 8)->default('outbound')->after('itinerary_quote_id')->index();
        });

        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->string('direction', 8)->default('outbound')->after('leg_order')->index();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE itinerary_quote_legs ADD CONSTRAINT itinerary_quote_legs_direction_check CHECK (direction IN ('outbound', 'inbound'))");
            DB::statement("ALTER TABLE reservation_segments ADD CONSTRAINT reservation_segments_direction_check CHECK (direction IN ('outbound', 'inbound'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservation_segments DROP CONSTRAINT IF EXISTS reservation_segments_direction_check');
            DB::statement('ALTER TABLE itinerary_quote_legs DROP CONSTRAINT IF EXISTS itinerary_quote_legs_direction_check');
        }

        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->dropIndex(['direction']);
            $table->dropColumn('direction');
        });

        Schema::table('itinerary_quote_legs', function (Blueprint $table): void {
            $table->dropIndex(['direction']);
            $table->dropColumn('direction');
        });
    }
};
