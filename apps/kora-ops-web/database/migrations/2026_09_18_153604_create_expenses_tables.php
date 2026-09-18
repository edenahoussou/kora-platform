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
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 64)->unique();
            $table->string('label', 160);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 32)->unique();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignId('cash_ledger_id')->nullable()->constrained('cash_ledgers')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->string('payment_channel', 32)->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('payee_label', 160);
            $table->string('payee_reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status', 'occurred_at']);
            $table->index(['expense_category_id', 'status', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
