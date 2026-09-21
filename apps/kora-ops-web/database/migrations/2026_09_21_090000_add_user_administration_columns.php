<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_phone_unique');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX users_phone_active_unique ON users (phone) WHERE deleted_at IS NULL');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('email', 'users_email_active_unique');
                $table->unique('phone', 'users_phone_active_unique');
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_email_active_unique');
            DB::statement('DROP INDEX IF EXISTS users_phone_active_unique');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_active_unique');
                $table->dropUnique('users_phone_active_unique');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email', 'users_email_unique');
            $table->unique('phone', 'users_phone_unique');
            $table->dropColumn('must_change_password');
            $table->dropSoftDeletes();
        });
    }
};
