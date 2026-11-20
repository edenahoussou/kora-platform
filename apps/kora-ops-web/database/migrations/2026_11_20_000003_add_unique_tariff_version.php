<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_tariffs', function (Blueprint $table): void {
            $table->unique(['corridor_id', 'deposit_kind', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('parcel_tariffs', function (Blueprint $table): void {
            $table->dropUnique(['corridor_id', 'deposit_kind', 'version']);
        });
    }
};
