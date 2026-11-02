<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $permissionId = DB::table('permissions')->where('guard_name', 'web')->where('name', 'kora-points.remit')->value('id');
            $roleId = DB::table('roles')->where('guard_name', 'web')->where('name', 'kora_point_supervisor')->value('id');

            if ($permissionId !== null && $roleId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('guard_name', 'web')->where('name', 'kora-points.remit')->value('id');
        $roleId = DB::table('roles')->where('guard_name', 'web')->where('name', 'kora_point_supervisor')->value('id');

        if ($permissionId !== null && $roleId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->where('role_id', $roleId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
