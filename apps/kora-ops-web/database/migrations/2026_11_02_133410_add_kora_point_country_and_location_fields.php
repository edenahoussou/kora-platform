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
        Schema::table('kora_point_organizations', function (Blueprint $table): void {
            $table->string('country', 2)->default('BJ')->after('name');
            $table->string('registration_identifier')->nullable()->after('country');
            $table->string('tax_identifier')->nullable()->after('registration_identifier');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('department')->nullable()->after('city');
        });

        Schema::table('kora_points', function (Blueprint $table): void {
            $table->string('phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kora_points', function (Blueprint $table): void {
            $table->string('phone')->nullable(false)->change();
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('department');
        });

        Schema::table('kora_point_organizations', function (Blueprint $table): void {
            $table->dropColumn(['country', 'registration_identifier', 'tax_identifier']);
        });
    }
};
