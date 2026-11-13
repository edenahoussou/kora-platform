<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('wallet_topup_provider_receipts', 'payment_provider_receipts');
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('parcel_charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['parcel_charge_id', 'idempotency_key']);
        });
        $sqlite = DB::getDriverName() === 'sqlite';
        if ($sqlite) {
            DB::statement('DROP INDEX fiscal_invoices_sale_reservation_unique');
        }
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
            $table->foreignId('parcel_charge_id')->nullable()->constrained()->restrictOnDelete();
        });
        if ($sqlite) {
            DB::statement("CREATE UNIQUE INDEX fiscal_invoices_sale_reservation_unique ON fiscal_invoices (reservation_id) WHERE document_kind = 'sale'");
        }
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_parcel_payable_exclusive CHECK (parcel_charge_id IS NULL OR reservation_id IS NULL)');
            DB::statement('ALTER TABLE fiscal_invoices ADD CONSTRAINT fiscal_invoices_parcel_payable_exclusive CHECK (parcel_charge_id IS NULL OR reservation_id IS NULL)');
        }
        DB::statement("CREATE UNIQUE INDEX fiscal_invoices_parcel_sale_unique ON fiscal_invoices (parcel_charge_id) WHERE document_kind = 'sale'");
    }

    public function down(): void
    {
        Schema::rename('payment_provider_receipts', 'wallet_topup_provider_receipts');
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('reservation_id')->nullable(false)->change();
        });
        DB::statement('DROP INDEX fiscal_invoices_parcel_sale_unique');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_parcel_payable_exclusive');
            DB::statement('ALTER TABLE fiscal_invoices DROP CONSTRAINT fiscal_invoices_parcel_payable_exclusive');
        }
        $sqlite = DB::getDriverName() === 'sqlite';
        if ($sqlite) {
            DB::statement('DROP INDEX fiscal_invoices_sale_reservation_unique');
        }
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parcel_charge_id');
            $table->unsignedBigInteger('reservation_id')->nullable(false)->change();
        });
        if ($sqlite) {
            DB::statement("CREATE UNIQUE INDEX fiscal_invoices_sale_reservation_unique ON fiscal_invoices (reservation_id) WHERE document_kind = 'sale'");
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['parcel_charge_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('parcel_charge_id');
        });
    }
};
