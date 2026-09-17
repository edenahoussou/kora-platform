<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('source_cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            $table->foreignId('destination_cash_register_id')->nullable()->constrained('cash_registers')->restrictOnDelete();
            $table->string('destination_type', 32)->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 24)->default('pending')->index();
            $table->string('reference', 255)->nullable()->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['agency_id', 'status', 'created_at']);
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('cash_transfer_id')->nullable()->after('cash_register_id')->constrained('cash_transfers')->restrictOnDelete();
            $table->index(['cash_transfer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropForeign(['cash_transfer_id']);
            $table->dropIndex(['cash_transfer_id', 'status']);
            $table->dropColumn('cash_transfer_id');
        });

        Schema::dropIfExists('cash_transfers');
    }
};
