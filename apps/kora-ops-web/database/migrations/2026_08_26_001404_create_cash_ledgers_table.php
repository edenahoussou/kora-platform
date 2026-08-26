<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->string('ledger_key', 64);
            $table->string('kind', 32)->index();
            $table->foreignId('agency_payment_account_id')->nullable()->constrained('agency_payment_accounts')->restrictOnDelete();
            $table->unsignedInteger('opening_balance')->default(0);
            $table->integer('expected_balance')->default(0);
            $table->integer('actual_balance')->nullable();
            $table->integer('variance')->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('opening_comment')->nullable();
            $table->text('closing_comment')->nullable();
            $table->timestamps();

            $table->unique(['cash_session_id', 'ledger_key']);
            $table->index(['cash_session_id', 'kind', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_ledgers');
    }
};
