<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\PermissionCatalog;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissionsAction
{
    /**
     * @param  list<string>  $permissions
     */
    public function execute(Role $role, array $permissions, mixed $actor): Role
    {
        abort_if($role->is_system && $role->name === 'system_admin', 422, 'Le rôle system_admin est protégé.');

        if ($role->category === RoleCategory::Terrain && ! in_array('departures.view', $permissions, true)) {
            abort(422, 'La permission departures.view est obligatoire pour un rôle terrain.');
        }

        $invalid = array_values(array_diff($permissions, PermissionCatalog::codes()));
        abort_if($invalid !== [], 422, 'La matrice contient une permission inconnue.');

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($role, $permissions): void {
            $role->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->refresh()->load('permissions');

        activity('identity-access')
            ->performedOn($role)
            ->causedBy($actor)
            ->withProperties([
                'before' => $before,
                'after' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])
            ->log('role_permissions_updated');

        return $role;
    }
}
