<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #342 — canal de distribution store pour l'application Passager.
 *
 * Les releases Convoyeur restent des APK stockés localement (`ops_apk`).
 * Les releases store (`store`) ne portent pas de binaire : elles référencent
 * le lien officiel du store Android/iOS et ne sont publiques qu'après
 * confirmation Ops et fin de rollout lorsqu'il est applicable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            $table->string('channel', 20)->default('ops_apk')->after('app');
            $table->string('platform', 10)->nullable()->after('channel');
            $table->string('store_url', 2048)->nullable()->after('platform');
            $table->string('environment', 20)->nullable()->after('store_url');
            $table->timestamp('publicly_confirmed_at')->nullable()->after('environment');
            $table->timestamp('rollout_completed_at')->nullable()->after('publicly_confirmed_at');
        });

        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropUnique(['app', 'build_number']);
            $table->unique(['app', 'channel', 'platform', 'environment', 'build_number']);
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropUnique(['app', 'channel', 'platform', 'environment', 'build_number']);
            $table->unique(['app', 'build_number']);
        });

        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropColumn([
                'channel',
                'platform',
                'store_url',
                'environment',
                'publicly_confirmed_at',
                'rollout_completed_at',
            ]);
        });
    }
};
