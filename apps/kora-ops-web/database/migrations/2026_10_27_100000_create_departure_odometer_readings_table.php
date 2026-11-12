<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet de bord OPS Mobile — relevés compteur (kilométrage) au départ et à
 * l'arrivée d'un voyage, saisis par le chauffeur ou le convoyeur. Un seul
 * relevé par type et par départ : une nouvelle saisie corrige la précédente
 * (tracée dans le journal d'activité).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departure_odometer_readings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('departure_id')->constrained('departures')->cascadeOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained('buses')->nullOnDelete();
            $table->string('kind', 8);
            $table->unsignedInteger('value_km');
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('terminal_p501_id')->nullable()->constrained('terminals_p501')->nullOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();

            $table->unique(['departure_id', 'kind']);
            $table->index(['bus_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departure_odometer_readings');
    }
};
