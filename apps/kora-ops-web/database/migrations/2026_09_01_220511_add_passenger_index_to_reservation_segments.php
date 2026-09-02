<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('passenger_index')->nullable()->after('leg_order');
            $table->index(['reservation_id', 'passenger_index', 'leg_order'], 'reservation_segments_passenger_matrix_idx');
            $table->unique(['reservation_id', 'passenger_index', 'leg_order'], 'reservation_segments_passenger_matrix_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->dropUnique('reservation_segments_passenger_matrix_unique');
            $table->dropIndex('reservation_segments_passenger_matrix_idx');
            $table->dropColumn('passenger_index');
        });
    }
};
