<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('itinerary_quote_id')->nullable()->constrained('itinerary_quotes')->nullOnDelete();
            $table->unsignedInteger('discount_amount');
            $table->timestamps();

            $table->unique(['promotion_id', 'reservation_id']);
            $table->index(['promotion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
    }
};
