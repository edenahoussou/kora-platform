<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itinerary_quotes', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->after('group_discount_amount')->constrained('promotions')->nullOnDelete();
            $table->unsignedInteger('promotion_discount_amount')->default(0)->after('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::table('itinerary_quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_discount_amount']);
        });
    }
};
