<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_idempotency_key_unique');
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique(['reservation_id', 'idempotency_key'], 'payments_reservation_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_reservation_idempotency_unique');
            $table->dropColumn('request_fingerprint');
            $table->unique('idempotency_key', 'payments_idempotency_key_unique');
        });
    }
};
