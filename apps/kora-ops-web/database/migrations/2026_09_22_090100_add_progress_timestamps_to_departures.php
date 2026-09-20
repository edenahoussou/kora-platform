<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Heures réelles de progression du départ, déclarées par le convoyeur
        // (heure locale du terminal) ou posées à la réception. Nullables :
        // les départs existants n'en ont pas.
        Schema::table('departures', function (Blueprint $table): void {
            $table->timestamp('boarding_started_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->dropColumn(['boarding_started_at', 'departed_at', 'arrived_at']);
        });
    }
};
