<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_models', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('brand');
            $table->string('name');
            $table->unsignedSmallInteger('default_seat_count');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['brand', 'name']);
        });

        Schema::create('bus_compositions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('seat_count');
            $table->json('layout');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('buses', function (Blueprint $table): void {
            $table->foreignId('bus_model_id')->nullable()->after('uuid')->constrained('bus_models')->restrictOnDelete();
            $table->foreignId('composition_id')->nullable()->after('bus_model_id')->constrained('bus_compositions')->restrictOnDelete();
            $table->foreignId('agency_id')->nullable()->after('composition_id')->constrained('agencies')->nullOnDelete();
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->dropForeign(['bus_model_id']);
            $table->dropForeign(['composition_id']);
            $table->dropForeign(['agency_id']);
            $table->dropIndex(['agency_id', 'status']);
            $table->dropColumn(['bus_model_id', 'composition_id', 'agency_id']);
        });

        Schema::dropIfExists('bus_compositions');
        Schema::dropIfExists('bus_models');
    }
};
