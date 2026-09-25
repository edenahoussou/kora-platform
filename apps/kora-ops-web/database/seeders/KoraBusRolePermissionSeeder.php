<?php

namespace Database\Seeders;

use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class KoraBusRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (SystemRole::allPermissions() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (SystemRole::cases() as $roleEnum) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleEnum->value,
                'guard_name' => 'web',
            ], [
                'is_system' => true,
                'label' => $roleEnum->translatedLabel(),
                'category' => $roleEnum->category()->value,
                'is_active' => true,
            ]);

            $role->forceFill([
                'label' => $roleEnum->translatedLabel(),
                'category' => $roleEnum->category()->value,
            ])->save();

            $permissions = $roleEnum === SystemRole::SystemAdmin
                ? SystemRole::allPermissions()
                : $roleEnum->permissions();

            $role->syncPermissions(
                Permission::query()
                    ->whereIn('name', $permissions)
                    ->where('guard_name', 'web')
                    ->get(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
