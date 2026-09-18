<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 32);
            $table->string('benefit_type', 32);
            $table->decimal('benefit_value', 12, 4);
            $table->string('scope_type', 32);
            $table->string('channel', 32)->nullable();
            $table->foreignId('line_id')->nullable()->constrained('lines')->nullOnDelete();
            $table->foreignId('origin_stop_id')->nullable()->constrained('stops')->nullOnDelete();
            $table->foreignId('destination_stop_id')->nullable()->constrained('stops')->nullOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope_type', 'status', 'priority']);
            $table->index(['line_id', 'status']);
        });

        // Un code promo ne peut être réutilisé que par une promotion vivante :
        // les promotions soft-deleted ne bloquent pas la réutilisation du code.
        DB::statement('CREATE UNIQUE INDEX promotions_code_unique ON promotions (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
