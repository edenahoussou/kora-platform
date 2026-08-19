<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fares', function (Blueprint $table): void {
            $table->foreignId('service_class_id')
                ->nullable()
                ->after('line_id')
                ->constrained('service_classes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fares', function (Blueprint $table): void {
            $table->dropForeign(['service_class_id']);
            $table->dropColumn('service_class_id');
        });
    }
};
