<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('passenger_count');
            $table->unsignedInteger('total_amount');
            $table->string('currency', 3);
            $table->json('snapshot');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('itinerary_quote_legs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('itinerary_quote_id')->constrained('itinerary_quotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('leg_order');
            $table->foreignId('price_quote_id')->constrained('price_quotes')->restrictOnDelete();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('origin_stop_id')->constrained('stops')->restrictOnDelete();
            $table->foreignId('destination_stop_id')->constrained('stops')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->unique(['itinerary_quote_id', 'leg_order']);
            $table->index(['departure_id', 'origin_stop_id', 'destination_stop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_quote_legs');
        Schema::dropIfExists('itinerary_quotes');
    }
};
