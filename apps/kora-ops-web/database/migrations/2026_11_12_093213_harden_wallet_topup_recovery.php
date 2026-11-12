<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->timestamp('next_reconciliation_at')->nullable()->index();
            $table->unsignedInteger('reconciliation_attempts')->default(0);
        });
        Schema::create('wallet_topup_request_keys', function (Blueprint $table): void {
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('request_key');
            $table->foreignId('wallet_topup_id')->constrained()->cascadeOnDelete();
            $table->primary(['wallet_id', 'request_key']);
        });
        Schema::create('wallet_topup_provider_receipts', function (Blueprint $table): void {
            $table->string('provider', 32);
            $table->string('transaction_reference', 128);
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->timestamp('ops_reported_at')->nullable();
            $table->primary(['provider', 'transaction_reference']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->json('wallet_funding')->nullable());
        Schema::table('users', function (Blueprint $table): void {
            $table->string('registration_origin', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('wallet_funding'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('registration_origin'));
        Schema::dropIfExists('wallet_topup_provider_receipts');
        Schema::dropIfExists('wallet_topup_request_keys');
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->dropUnique(['wallet_id', 'idempotency_key']);
            $table->unique('idempotency_key');
            $table->dropIndex(['next_reconciliation_at']);
            $table->dropColumn(['next_reconciliation_at', 'reconciliation_attempts']);
        });
    }
};
