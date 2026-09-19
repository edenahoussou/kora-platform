<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->after('itinerary_quote_id')->constrained('promotions')->nullOnDelete();
            $table->unsignedInteger('promotion_discount_amount')->default(0)->after('promotion_id');
        });

        // La remise portée par le devis devient la remise effective de la
        // réservation : elle reste déclarable au moment de la certification.
        DB::table('itinerary_quotes')
            ->whereNotNull('promotion_id')
            ->orderBy('id')
            ->get(['id', 'promotion_id', 'promotion_discount_amount'])
            ->each(function (object $quote): void {
                DB::table('reservations')
                    ->where('itinerary_quote_id', $quote->id)
                    ->update([
                        'promotion_id' => $quote->promotion_id,
                        'promotion_discount_amount' => $quote->promotion_discount_amount,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_discount_amount']);
        });
    }
};
