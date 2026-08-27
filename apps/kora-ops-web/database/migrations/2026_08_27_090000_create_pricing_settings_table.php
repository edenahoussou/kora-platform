<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages globaux du moteur de tarification dynamique (CDC §9.2 : "Plancher/plafond
     * paramétrables par la Direction Commerciale"). Table à ligne unique (id = 1) : le
     * plancher/plafond borne le coefficient final toutes règles de pricing confondues,
     * ce n'est pas un réglage par règle.
     */
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('floor_coefficient', 6, 4)->nullable();
            $table->decimal('ceiling_coefficient', 6, 4)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
