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
                'label' => __('system_role.'.$roleEnum->value),
                'category' => $roleEnum->category()->value,
                'is_active' => true,
            ]);

            // Toujours resynchroniser, pas seulement à la création du rôle
            // (corrigé le 24/09/2026) : un rôle système existant (ex.
            // `conductor`, créé bien avant ce correctif) ne recevait
            // jamais les permissions ajoutées après coup à
            // `SystemRole::permissions()` — `tracking.report`,
            // `tracking.view-agency`, `tracking.view-network` (MEZ-100/101)
            // sont restées absentes en base malgré plusieurs relances de ce
            // seeder, silencieusement. `syncPermissions` est idempotent :
            // aucun risque à l'appeler à chaque exécution, y compris pour
            // un rôle inchangé. Ne concerne que les rôles SYSTÈME
            // (`SystemRole::cases()`) — jamais les rôles personnalisés créés
            // depuis l'administration, hors de cette boucle.
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

// namespace Database\Seeders;

// use App\Modules\IdentityAccess\Data\SystemRole;
// use App\Modules\IdentityAccess\Models\Role;
// use Illuminate\Database\Seeder;
// use Spatie\Permission\Models\Permission;
// use Spatie\Permission\PermissionRegistrar;

// class KoraBusRolePermissionSeeder extends Seeder
// {
//     public function run(): void
//     {
//         app(PermissionRegistrar::class)->forgetCachedPermissions();

//         foreach (SystemRole::allPermissions() as $permission) {
//             Permission::query()->firstOrCreate([
//                 'name' => $permission,
//                 'guard_name' => 'web',
//             ]);
//         }

//         foreach (SystemRole::cases() as $roleEnum) {
//             $role = Role::query()->firstOrCreate([
//                 'name' => $roleEnum->value,
//                 'guard_name' => 'web',
//             ], [
//                 'is_system' => true,
//                 'label' => __('system_role.'.$roleEnum->value),
//                 'category' => $roleEnum->category()->value,
//                 'is_active' => true,
//             ]);

//             if ($role->wasRecentlyCreated) {
//                 $permissions = $roleEnum === SystemRole::SystemAdmin
//                     ? SystemRole::allPermissions()
//                     : $roleEnum->permissions();

//                 $role->syncPermissions(
//                     Permission::query()
//                         ->whereIn('name', $permissions)
//                         ->where('guard_name', 'web')
//                         ->get(),
//                 );
//             }
//         }

//         app(PermissionRegistrar::class)->forgetCachedPermissions();
//     }
// }
