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
            foreach (['kora-points.manage', 'kora-points.documents.upload'] as $name) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $permissions = DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn('name', ['kora-points.manage', 'kora-points.documents.upload'])
                ->pluck('id', 'name');
            $roles = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', ['kora_point_supervisor', 'kora_point_treasurer', 'commercial_director']);

            foreach ($roles->get() as $role) {
                $rolePermissions = $role->name === 'kora_point_supervisor'
                    ? ['kora-points.manage', 'kora-points.documents.upload']
                    : ['kora-points.documents.upload'];

                foreach ($rolePermissions as $permissionName) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissions[$permissionName],
                        'role_id' => $role->id,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $managePermissionId = DB::table('permissions')
                ->where('guard_name', 'web')
                ->where('name', 'kora-points.manage')
                ->value('id');
            $uploadPermissionId = DB::table('permissions')
                ->where('guard_name', 'web')
                ->where('name', 'kora-points.documents.upload')
                ->value('id');
            $partnerSupervisorId = DB::table('roles')
                ->where('guard_name', 'web')
                ->where('name', 'kora_point_supervisor')
                ->value('id');

            if ($managePermissionId !== null && $partnerSupervisorId !== null) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $managePermissionId)
                    ->where('role_id', $partnerSupervisorId)
                    ->delete();
            }

            if ($uploadPermissionId !== null) {
                $roleIds = DB::table('roles')
                    ->where('guard_name', 'web')
                    ->whereIn('name', ['kora_point_supervisor', 'kora_point_treasurer', 'commercial_director'])
                    ->pluck('id');

                DB::table('role_has_permissions')
                    ->where('permission_id', $uploadPermissionId)
                    ->whereIn('role_id', $roleIds)
                    ->delete();

                if (! DB::table('role_has_permissions')->where('permission_id', $uploadPermissionId)->exists()) {
                    DB::table('permissions')->where('id', $uploadPermissionId)->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
