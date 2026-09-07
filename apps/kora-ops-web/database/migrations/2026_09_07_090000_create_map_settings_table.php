<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglage admin du fournisseur cartographique (lignes/arrêts). Table à
     * ligne unique, sur le même modèle que pricing_settings : `provider`
     * nullable signifie "pas de réglage admin, on retombe sur MAP_PROVIDER
     * dans .env" (openstreetmap par défaut).
     */
    public function up(): void
    {
        Schema::create('map_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_settings');
    }
};
