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
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('theme');
            $table->boolean('dispatch_alerts')->default(true)->after('timezone');
            $table->boolean('cashier_alerts')->default(true)->after('dispatch_alerts');
            $table->boolean('security_alerts')->default(true)->after('cashier_alerts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'dispatch_alerts', 'cashier_alerts', 'security_alerts']);
        });
    }
};
