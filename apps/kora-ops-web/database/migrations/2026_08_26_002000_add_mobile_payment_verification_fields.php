<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_transaction_id', 128)->nullable()->after('provider_reference');
            $table->string('provider_currency', 3)->nullable()->after('currency');
            $table->unsignedInteger('provider_amount')->nullable()->after('provider_currency');
            $table->timestamp('last_verified_at')->nullable()->after('verified_at');
            $table->string('failure_code', 64)->nullable()->after('last_verified_at');
            $table->text('failure_message')->nullable()->after('failure_code');
            $table->index(['provider', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'provider_transaction_id']);
            $table->dropColumn([
                'provider_transaction_id',
                'provider_currency',
                'provider_amount',
                'last_verified_at',
                'failure_code',
                'failure_message',
            ]);
        });
    }
};
