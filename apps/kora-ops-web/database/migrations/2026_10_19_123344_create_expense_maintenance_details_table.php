<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_maintenance_details', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('expense_id')->unique()->constrained('expenses')->cascadeOnDelete();
            $table->string('maintenance_type', 32);
            $table->string('garage_label', 160)->nullable();
            $table->unsignedInteger('parts_amount')->nullable();
            $table->unsignedInteger('labor_amount')->nullable();
            $table->date('immobilized_from')->nullable();
            $table->date('immobilized_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_maintenance_details');
    }
};
