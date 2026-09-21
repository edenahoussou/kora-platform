<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->string('document_kind', 32)->default('sale')->after('status');
            $table->foreignId('original_fiscal_invoice_id')->nullable()->after('document_kind')->constrained('fiscal_invoices')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->after('original_fiscal_invoice_id')->constrained('users')->nullOnDelete();
            $table->text('credit_note_reason')->nullable()->after('requested_by_user_id');
            $table->uuid('credit_note_idempotency_key')->nullable()->after('credit_note_reason');
            $table->text('source_snapshot')->nullable()->after('tax_snapshot');
            $table->timestamp('requested_at')->nullable()->after('fiscalized_at');

            $table->unique('credit_note_idempotency_key');
            $table->index(['document_kind', 'status']);
            $table->index(['original_fiscal_invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_invoices', function (Blueprint $table): void {
            $table->dropIndex(['original_fiscal_invoice_id', 'status']);
            $table->dropIndex(['document_kind', 'status']);
            $table->dropUnique(['credit_note_idempotency_key']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['original_fiscal_invoice_id']);
            $table->dropColumn([
                'document_kind',
                'original_fiscal_invoice_id',
                'requested_by_user_id',
                'credit_note_reason',
                'credit_note_idempotency_key',
                'source_snapshot',
                'requested_at',
            ]);
        });
    }
};
