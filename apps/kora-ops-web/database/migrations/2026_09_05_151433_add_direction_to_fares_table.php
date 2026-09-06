<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fares', function (Blueprint $table): void {
            $table->string('direction', 16)->default('both')->after('service_class_id');
            $table->index(['line_id', 'origin_stop_id', 'destination_stop_id', 'direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fares', function (Blueprint $table): void {
            $table->dropIndex(['fares_line_id_origin_stop_id_destination_stop_id_direction_status_index']);
            $table->dropColumn('direction');
        });
    }
};
