<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            // Heure locale du terminal au moment du signalement (le convoyeur
            // peut signaler hors ligne : `created_at` est l'heure de réception).
            $table->timestamp('occurred_at')->nullable()->after('longitude');

            // Terminal P501 (ou téléphone) qui a envoyé le signalement — audit.
            $table->foreignId('terminal_p501_id')->nullable()->after('created_by')
                ->constrained('terminals_p501')->nullOnDelete();

            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['created_by', 'created_at']);
            $table->dropConstrainedForeignId('terminal_p501_id');
            $table->dropColumn('occurred_at');
        });
    }
};
