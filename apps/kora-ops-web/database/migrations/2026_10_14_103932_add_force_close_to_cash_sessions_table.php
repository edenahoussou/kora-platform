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
            $table->foreignId('force_closed_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('force_closed_at')->nullable()->after('force_closed_by');
        });

        $this->restorePartialIndexes();
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('force_closed_by');
            $table->dropColumn('force_closed_at');
        });

        $this->restorePartialIndexes();
    }

    /** SQLite table rebuilds strip the WHERE clause from these unique indexes. */
    private function restorePartialIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_register');
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_agent');
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_register ON cash_sessions (cash_register_id) WHERE status = 'open'");
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_agent ON cash_sessions (agent_id) WHERE status = 'open'");
    }
};
