<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            // Distinct from last_sync_at: updated on every successful terminal
            // handshake (login of the field session), regardless of whether a
            // data sync actually happened during that session.
            $table->timestamp('last_handshake_at')->nullable()->after('last_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            $table->dropColumn('last_handshake_at');
        });
    }
};
