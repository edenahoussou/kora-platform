<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            $table->string('hardware_model', 128)->nullable()->after('type');
            $table->string('os_version', 64)->nullable()->after('hardware_model');
            $table->string('app_version', 64)->nullable()->after('os_version');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('terminals_p501', function (Blueprint $table): void {
            $table->dropIndex(['terminals_p501_type_status_index']);
            $table->dropColumn(['hardware_model', 'os_version', 'app_version']);
        });
    }
};
