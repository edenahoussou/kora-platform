<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table): void {
            $table->dropUnique('stops_line_id_stop_order_unique');
        });

        DB::statement('CREATE UNIQUE INDEX stops_line_id_stop_order_unique ON stops (line_id, stop_order) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stops_line_id_stop_order_unique');

        Schema::table('stops', function (Blueprint $table): void {
            $table->unique(['line_id', 'stop_order']);
        });
    }
};
