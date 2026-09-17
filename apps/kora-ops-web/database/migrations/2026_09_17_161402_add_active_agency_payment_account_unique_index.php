<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX agency_payment_accounts_one_active_network_unique ON agency_payment_accounts (agency_id, network) WHERE status = 'active' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX agency_payment_accounts_one_active_network_unique');
    }
};
