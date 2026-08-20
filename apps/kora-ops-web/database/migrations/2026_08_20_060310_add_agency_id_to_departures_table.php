<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->foreignId('agency_id')
                ->nullable()
                ->after('bus_id')
                ->constrained('agencies')
                ->nullOnDelete();
            $table->index(['agency_id', 'departure_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->dropIndex(['agency_id', 'departure_date', 'status']);
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });
    }
};
