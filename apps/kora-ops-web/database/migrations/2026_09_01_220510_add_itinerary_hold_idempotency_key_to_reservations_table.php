<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('channel');
            $table->unique(['user_id', 'idempotency_key'], 'reservations_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
