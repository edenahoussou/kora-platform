<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->dropUnique('cash_sessions_agent_id_agency_id_business_date_unique');
        });

        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_register ON cash_sessions (cash_register_id) WHERE status = 'open'");
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_agent ON cash_sessions (agent_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_register');
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_agent');

        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->unique(['agent_id', 'agency_id', 'business_date']);
        });
    }
};
