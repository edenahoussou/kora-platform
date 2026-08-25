<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('network', 32);
            $table->string('label', 120);
            $table->string('account_identifier', 64);
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agency_id', 'network', 'account_identifier']);
            $table->index(['agency_id', 'network', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('method', 32)->nullable()->after('reservation_id')->index();
            $table->foreignId('agency_payment_account_id')->nullable()->after('method')->constrained('agency_payment_accounts')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->after('agency_payment_account_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->string('mobile_money_network', 32)->nullable()->after('cash_session_id')->index();
            $table->string('payer_phone', 32)->nullable()->after('mobile_money_network');
            $table->string('payer_name', 120)->nullable()->after('payer_phone');
            $table->unsignedInteger('cash_received_amount')->nullable()->after('amount');
            $table->unsignedInteger('change_amount')->nullable()->after('cash_received_amount');
            $table->foreignId('verified_by')->nullable()->after('change_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['cash_session_id']);
            $table->dropForeign(['agency_payment_account_id']);
            $table->dropIndex(['mobile_money_network']);
            $table->dropIndex(['method']);
            $table->dropColumn([
                'method',
                'agency_payment_account_id',
                'cash_session_id',
                'mobile_money_network',
                'payer_phone',
                'payer_name',
                'cash_received_amount',
                'change_amount',
                'verified_by',
                'verified_at',
            ]);
        });

        Schema::dropIfExists('agency_payment_accounts');
    }
};
