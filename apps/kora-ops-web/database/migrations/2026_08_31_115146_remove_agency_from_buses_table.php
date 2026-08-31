<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->dropIndex(['agency_id', 'status']);
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->foreignId('agency_id')->nullable()->after('composition_id')->constrained('agencies')->nullOnDelete();
            $table->index(['agency_id', 'status']);
        });
    }
};
