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
        Schema::table('company_accounts', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('email');
            $table->string('city')->nullable()->after('address');
            $table->string('country', 2)->nullable()->after('city');
            $table->string('ifu', 64)->nullable()->after('country');
            $table->string('rccm', 128)->nullable()->after('ifu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_accounts', function (Blueprint $table): void {
            $table->dropColumn(['address', 'city', 'country', 'ifu', 'rccm']);
        });
    }
};
