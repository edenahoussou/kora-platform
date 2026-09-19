<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            // `expires_at` nul = session antérieure à MEZ-95 : considérée comme
            // expirée (jamais prolongée silencieusement, le terminal doit
            // rouvrir une session).
            $table->timestamp('expires_at')->nullable()->after('last_seen_at');
            $table->timestamp('absolute_expires_at')->nullable()->after('expires_at');
            $table->timestamp('renewed_at')->nullable()->after('absolute_expires_at');
            $table->unsignedInteger('renewal_count')->default(0)->after('renewed_at');

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('terminal_sessions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'revoked_at']);
            $table->dropColumn(['expires_at', 'absolute_expires_at', 'renewed_at', 'renewal_count']);
        });
    }
};
