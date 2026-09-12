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
        Schema::table('whatsapp_deliveries', function (Blueprint $table): void {
            $table->dropUnique('whatsapp_deliveries_ticket_id_provider_unique');
            $table->unique('ticket_id', 'whatsapp_deliveries_ticket_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_deliveries', function (Blueprint $table): void {
            $table->dropUnique('whatsapp_deliveries_ticket_id_unique');
            $table->unique(['ticket_id', 'provider'], 'whatsapp_deliveries_ticket_id_provider_unique');
        });
    }
};
