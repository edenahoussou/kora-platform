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
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('expense_id')->nullable()->after('cash_transfer_id')->constrained('expenses')->nullOnDelete();
            $table->unique('expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropUnique(['expense_id']);
            $table->dropForeign(['expense_id']);
            $table->dropColumn('expense_id');
        });
    }
};
