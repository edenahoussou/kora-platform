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
        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->timestamp('opened_at')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('opened_at');
            $table->timestamp('closed_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('cash_ledger_id')->nullable()->after('cash_session_id')->constrained('cash_ledgers')->restrictOnDelete();
            $table->string('direction', 8)->default('in')->after('type')->index();
            $table->string('status', 16)->default('posted')->after('direction')->index();
            $table->string('reference', 255)->nullable()->after('description')->index();
            $table->string('destination_label', 160)->nullable()->after('reference');
            $table->string('destination_reference', 255)->nullable()->after('destination_label');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestamp('occurred_at')->nullable()->after('rejection_reason')->index();
            $table->index(['cash_ledger_id', 'status', 'occurred_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('cash_ledger_id')->nullable()->after('cash_session_id')->constrained('cash_ledgers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['cash_ledger_id']);
            $table->dropColumn('cash_ledger_id');
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropForeign(['cash_ledger_id']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['cash_ledger_id', 'status', 'occurred_at']);
            $table->dropIndex(['direction']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reference']);
            $table->dropColumn([
                'cash_ledger_id',
                'direction',
                'status',
                'reference',
                'destination_label',
                'destination_reference',
                'approved_by',
                'approved_at',
                'rejected_at',
                'rejection_reason',
                'occurred_at',
            ]);
        });

        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['opened_at', 'submitted_at', 'closed_at', 'approved_by', 'approved_at']);
        });
    }
};
