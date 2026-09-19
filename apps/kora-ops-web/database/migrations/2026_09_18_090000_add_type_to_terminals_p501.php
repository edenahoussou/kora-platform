<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            // Voir App\Modules\IdentityAccess\Data\TerminalType — 'p501' par
            // défaut pour que les terminaux déjà enregistrés (avant ce
            // ticket) restent corrects sans backfill manuel. Ticket
            // "Supporter le scan par caméra téléphone en alternative au
            // P501".
            $table->string('type', 16)->default('p501')->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
