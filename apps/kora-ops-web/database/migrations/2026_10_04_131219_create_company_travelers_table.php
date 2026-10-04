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
        Schema::create('company_travelers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_account_id')->constrained('company_accounts')->restrictOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('document_type', 32)->nullable();
            $table->string('document_number', 64)->nullable();
            $table->string('nationality', 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('yellow_fever_vaccinated')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_account_id', 'phone']);
            $table->index(['company_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_travelers');
    }
};
