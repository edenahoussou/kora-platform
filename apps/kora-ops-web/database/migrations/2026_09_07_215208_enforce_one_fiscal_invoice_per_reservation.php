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
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->unique('reservation_id', 'fiscal_invoices_reservation_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropUnique('fiscal_invoices_reservation_id_unique');
        });
    }
};
