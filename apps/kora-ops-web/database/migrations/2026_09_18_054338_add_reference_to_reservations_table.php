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
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference', 12)->nullable()->after('uuid');
        });

        DB::table('reservations')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(500, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    $suffix = strtoupper(str_pad(base_convert((string) $reservation->id, 10, 36), 8, '0', STR_PAD_LEFT));

                    DB::table('reservations')
                        ->where('id', $reservation->id)
                        ->update(['reference' => 'KBR-'.$suffix]);
                }
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference', 12)->nullable(false)->change();
            $table->unique('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
