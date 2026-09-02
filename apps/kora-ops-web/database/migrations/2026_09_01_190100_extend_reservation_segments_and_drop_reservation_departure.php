<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->unsignedInteger('leg_order')->default(1)->after('reservation_id');
            $table->index(['reservation_id', 'leg_order']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['departure_id']);
            $table->dropIndex(['departure_id', 'status']);
            $table->dropColumn('departure_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('departure_id')
                ->after('user_id')
                ->constrained('departures')
                ->restrictOnDelete();
            $table->index(['departure_id', 'status']);
        });

        Schema::table('reservation_segments', function (Blueprint $table): void {
            $table->dropIndex(['reservation_id', 'leg_order']);
            $table->dropColumn('leg_order');
        });
    }
};
