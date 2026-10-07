<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les avoirs partagent la reservation_id de leur facture d'origine :
     * l'unicité ne doit valoir que pour les factures de vente.
     */
    public function up(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropUnique('fiscal_invoices_reservation_id_unique');
        });

        DB::statement("CREATE UNIQUE INDEX fiscal_invoices_sale_reservation_unique ON fiscal_invoices (reservation_id) WHERE document_kind = 'sale'");
    }

    public function down(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropIndex('fiscal_invoices_sale_reservation_unique');
            $table->unique('reservation_id');
        });
    }
};
