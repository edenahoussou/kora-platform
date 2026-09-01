<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_departures', function (Blueprint $table): void {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('planned_departures', function (Blueprint $table): void {
            $table->foreignId('agency_id')->after('line_id')->constrained('agencies')->restrictOnDelete();
        });
    }
};
