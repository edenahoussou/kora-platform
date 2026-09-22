<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users.view', 'users.manage', 'roles.view', 'roles.manage'] as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleId = DB::table('roles')
            ->where('name', 'system_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['users.view', 'users.manage', 'roles.view', 'roles.manage'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['users.view', 'users.manage', 'roles.view', 'roles.manage'])
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
