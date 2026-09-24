<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            $table->index(
                ['terminal_p501_id', 'revoked_at', 'expires_at', 'last_seen_at'],
                'terminal_sessions_supervision_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            $table->dropIndex('terminal_sessions_supervision_index');
        });
    }
};
