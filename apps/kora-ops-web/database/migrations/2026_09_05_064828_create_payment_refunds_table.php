<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignId('reservation_passenger_id')->nullable()->constrained('reservation_passengers')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('destination', 32);
            $table->string('status', 32)->default('pending')->index();
            $table->string('reference')->unique();
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'reservation_passenger_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
