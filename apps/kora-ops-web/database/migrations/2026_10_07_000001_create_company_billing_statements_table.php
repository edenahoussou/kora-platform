<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_billing_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_account_id')->constrained('company_accounts')->restrictOnDelete();
            $table->string('period_key', 7);
            $table->string('reference')->unique();
            $table->string('status', 16)->default('generated');
            $table->string('currency', 3)->default('XOF');
            $table->unsignedInteger('debit_total')->default(0);
            $table->unsignedInteger('refund_total')->default(0);
            $table->integer('net_total')->default(0);
            $table->unsignedSmallInteger('reservations_count')->default(0);
            $table->unsignedSmallInteger('invoices_count')->default(0);
            $table->json('lines')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_account_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_billing_statements');
    }
};
