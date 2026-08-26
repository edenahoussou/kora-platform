<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_provider_provider_reference_unique');
            $table->unique(
                ['provider', 'mobile_money_network', 'agency_payment_account_id', 'provider_reference'],
                'payments_manual_mobile_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_manual_mobile_reference_unique');
            $table->unique(['provider', 'provider_reference']);
        });
    }
};
