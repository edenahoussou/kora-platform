<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agency_code_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('next_value')->default(1);
        });

        // Singleton row (id = 1) that GenerateAgencyCodeAction locks and increments
        // atomically to hand out unique, sequential agency codes.
        DB::table('agency_code_sequences')->insert(['id' => 1, 'next_value' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_code_sequences');
    }
};
