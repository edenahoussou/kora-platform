<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une recharge Wallet est un paiement provider sans réservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reservation_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reservation_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable(false)->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->restrictOnDelete();
        });
    }
};
