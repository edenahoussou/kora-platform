<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('bus_id')->nullable()->after('agency_id')->constrained('buses')->restrictOnDelete();
            $table->foreignId('line_id')->nullable()->after('bus_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('departure_id')->nullable()->after('line_id')->constrained('departures')->restrictOnDelete();
            $table->index(['bus_id', 'occurred_at']);
            $table->index(['line_id', 'occurred_at']);
            $table->index(['departure_id', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['bus_id', 'occurred_at']);
            $table->dropIndex(['line_id', 'occurred_at']);
            $table->dropIndex(['departure_id', 'occurred_at']);
            $table->dropIndex(['status', 'occurred_at']);
            $table->dropConstrainedForeignId('bus_id');
            $table->dropConstrainedForeignId('line_id');
            $table->dropConstrainedForeignId('departure_id');
        });
    }
};
