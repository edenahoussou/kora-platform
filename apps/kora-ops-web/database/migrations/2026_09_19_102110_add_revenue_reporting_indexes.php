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
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'confirmed_at'], 'payments_status_confirmed_at_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->index('created_at', 'payment_refunds_created_at_index');
            $table->index(['payment_id', 'created_at'], 'payment_refunds_payment_created_at_index');
        });

        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->index('payment_id', 'fiscal_invoices_payment_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropIndex('fiscal_invoices_payment_id_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex('payment_refunds_created_at_index');
            $table->dropIndex('payment_refunds_payment_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_confirmed_at_index');
        });
    }
};
