<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('itinerary_quote_id')
                ->nullable()
                ->after('user_id')
                ->constrained('itinerary_quotes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['itinerary_quote_id']);
            $table->dropColumn('itinerary_quote_id');
        });
    }
};
