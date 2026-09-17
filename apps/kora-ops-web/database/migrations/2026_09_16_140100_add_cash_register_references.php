<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('cash_session_id')->nullable()->change();
        });

        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->foreignId('cash_register_id')->nullable()->after('agency_id')->constrained('cash_registers')->restrictOnDelete();
            $table->index(['cash_register_id', 'status']);
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('cash_register_id')->nullable()->after('cash_ledger_id')->constrained('cash_registers')->restrictOnDelete();
            $table->index(['cash_register_id', 'status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropForeign(['cash_register_id']);
            $table->dropIndex(['cash_register_id', 'status', 'occurred_at']);
            $table->dropColumn('cash_register_id');
        });

        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->dropForeign(['cash_register_id']);
            $table->dropIndex(['cash_register_id', 'status']);
            $table->dropColumn('cash_register_id');
        });
    }
};
