<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_amenities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 64)->unique();
            $table->string('label');
            $table->string('icon', 64)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bus_bus_amenity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->foreignId('bus_amenity_id')->constrained('bus_amenities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bus_id', 'bus_amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_bus_amenity');
        Schema::dropIfExists('bus_amenities');
    }
};
