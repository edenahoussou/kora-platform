<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_release_events', function (Blueprint $table): void {
            // Manquantes du schéma d'origine :
            // - terminal_p501_id : le ticket demande explicitement de
            //   journaliser le terminal, comme boarding_events le fait déjà.
            // - conflict_payload : détail lisible (code, message, action
            //   recommandée) pour les événements rejetés/en conflit — même
            //   rôle que sur boarding_events.
            // Uniquement des colonnes nouvelles (ADD), aucune modification
            // des colonnes NOT NULL existantes : doctrine/dbal n'est pas
            // installé, donc pas d'ALTER COLUMN possible ici.
            $table->foreignId('terminal_p501_id')->nullable()->after('actual_stop_id')
                ->constrained('terminals_p501')->nullOnDelete();
            $table->json('conflict_payload')->nullable()->after('released_segments');
        });
    }

    public function down(): void
    {
        Schema::table('seat_release_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terminal_p501_id');
            $table->dropColumn('conflict_payload');
        });
    }
};
