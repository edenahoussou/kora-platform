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
        Schema::create('fiscal_test_invoice_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedTinyInteger('case_number')->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_run_id')->nullable()->constrained('fiscal_test_invoice_runs')->nullOnDelete();
            $table->foreignId('emecf_invoice_id')->nullable()->constrained('emecf_invoices')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->text('request_snapshot')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_test_invoice_runs');
    }
};
