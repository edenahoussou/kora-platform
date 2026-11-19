<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_hubs', function (Blueprint $table): void {
            $table->boolean('supports_weighing')->default(true)->after('enabled');
        });

        Schema::table('parcels', function (Blueprint $table): void {
            $table->string('inspection_category', 32)->nullable()->after('weight_grams');
            $table->text('inspection_refusal_reason')->nullable()->after('inspection_category');
            $table->timestampTz('inspected_at')->nullable()->index()->after('inspection_refusal_reason');
            $table->unsignedBigInteger('inspection_hub_id')->nullable()->after('inspected_at');
            $table->index('inspection_hub_id');
            $table->foreign('inspection_hub_id')->references('id')->on('parcel_hubs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropForeign(['inspection_hub_id']);
            $table->dropIndex(['inspection_hub_id']);
            $table->dropIndex(['inspected_at']);
            $table->dropColumn(['inspection_category', 'inspection_refusal_reason', 'inspected_at', 'inspection_hub_id']);
        });

        Schema::table('parcel_hubs', function (Blueprint $table): void {
            $table->dropColumn('supports_weighing');
        });
    }
};
