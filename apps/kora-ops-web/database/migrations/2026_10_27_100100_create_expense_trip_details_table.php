<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet de bord OPS Mobile — détails terrain d'une dépense de voyage
 * (carburant, péage, taxe frontière) saisie par l'équipage. Table satellite
 * 1:1 de `expenses`, sur le modèle de `expense_maintenance_details` : la
 * dépense elle-même suit le circuit standard (en attente → approuvée /
 * rejetée) et alimente la rentabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_trip_details', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('expense_id')->unique()->constrained('expenses')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->decimal('liters', 8, 2)->nullable();
            $table->unsignedInteger('unit_price')->nullable();
            $table->string('station_label', 160)->nullable();
            $table->string('checkpoint_label', 160)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('source', 16)->default('ops_mobile');
            $table->uuid('client_uuid')->unique();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('terminal_p501_id')->nullable()->constrained('terminals_p501')->nullOnDelete();
            $table->timestamps();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_trip_details');
    }
};
