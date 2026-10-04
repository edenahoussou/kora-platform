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
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('phone');
            $table->string('department', 120)->nullable()->after('job_title');
            $table->string('location', 160)->nullable()->after('department');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'department', 'location', 'password_changed_at']);
        });
    }
};
