<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La colonne porte désormais l'identifiant de livraison du challenge
     * (email OU téléphone international) : l'inscription et la connexion
     * acceptent les deux. Les lignes existantes restent valides — elles
     * contiennent uniquement des emails, qui sont des identifiants.
     */
    public function up(): void
    {
        Schema::table('auth_otp_challenges', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->dropIndex(['email', 'purpose', 'status']);
            $table->renameColumn('email', 'identifier');
            $table->index('identifier');
            $table->index(['identifier', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('auth_otp_challenges', function (Blueprint $table): void {
            $table->dropIndex(['identifier']);
            $table->dropIndex(['identifier', 'purpose', 'status']);
            $table->renameColumn('identifier', 'email');
            $table->index('email');
            $table->index(['email', 'purpose', 'status']);
        });
    }
};
