<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->json('payer_snapshot')->nullable()->after('group_size');
        });

        Schema::table('reservation_passengers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('passenger_index')->nullable()->after('status');
            $table->boolean('yellow_fever_vaccinated')->nullable()->after('birth_date');
        });

        DB::table('reservations')->orderBy('id')->each(function (object $reservation): void {
            $passengers = DB::table('reservation_passengers')
                ->where('reservation_id', $reservation->id)
                ->orderBy('id')
                ->get();

            foreach ($passengers as $fallbackIndex => $passenger) {
                $segmentIndex = DB::table('reservation_segments')
                    ->where('reservation_passenger_id', $passenger->id)
                    ->whereNotNull('passenger_index')
                    ->min('passenger_index');

                DB::table('reservation_passengers')
                    ->where('id', $passenger->id)
                    ->update(['passenger_index' => $segmentIndex ?? $fallbackIndex]);
            }
        });

        Schema::table('reservation_passengers', function (Blueprint $table): void {
            $table->unique(['reservation_id', 'passenger_index'], 'reservation_passengers_reservation_index_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_passengers', function (Blueprint $table): void {
            $table->dropUnique('reservation_passengers_reservation_index_unique');
            $table->dropColumn(['passenger_index', 'yellow_fever_vaccinated']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn('payer_snapshot');
        });
    }
};
