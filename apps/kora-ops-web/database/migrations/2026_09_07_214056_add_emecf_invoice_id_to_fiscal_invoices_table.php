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
            $table->foreignId('emecf_invoice_id')->nullable()->after('mecef_uid')->constrained('emecf_invoices')->nullOnDelete();
            $table->unique('emecf_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropForeign(['emecf_invoice_id']);
            $table->dropUnique(['emecf_invoice_id']);
            $table->dropColumn('emecf_invoice_id');
        });
    }
};
