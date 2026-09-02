<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->index(['reservation_id', 'reservation_passenger_id', 'leg_order'], 'reservation_segments_passenger_leg_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE reservation_segments ADD CONSTRAINT reservation_segments_valid_leg_order CHECK (leg_order > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->dropIndex('reservation_segments_passenger_leg_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservation_segments DROP CONSTRAINT IF EXISTS reservation_segments_valid_leg_order');
        }
    }
};
