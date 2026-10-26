<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEZ-150 — distribution hors store des applications mobiles.
 *
 * Une ligne = un package (APK) publié pour une application (`app`, V1 :
 * `ops_mobile`). Le binaire vit sur le disque privé `releases`
 * (storage/app/releases, jamais exposé par le serveur web) et n'est servi
 * que par les routes publiques `downloads.apps.*`.
 *
 * - `build_number` est le `versionCode` Android : unique par application,
 *   car Android refuse d'installer deux fois le même code (et toute
 *   rétrogradation).
 * - `archived_at` retire la release du calcul « dernière version » sans la
 *   supprimer (rollback possible en la restaurant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('app', 32);
            $table->string('version', 64);
            $table->unsignedInteger('build_number');
            $table->string('artifact_path', 255);
            $table->string('original_filename', 255)->nullable();
            $table->unsignedBigInteger('artifact_size');
            $table->char('sha256', 64);
            $table->text('release_notes')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['app', 'build_number']);
            $table->index(['app', 'archived_at', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
