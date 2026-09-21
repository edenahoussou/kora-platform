<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\PermissionCatalog;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCustomRoleAction
{
    /**
     * @param  array{label: string, description: string|null, category: string, permissions: list<string>}  $attributes
     */
    public function execute(array $attributes, mixed $actor): Role
    {
        $permissions = $this->permissions($attributes['permissions']);

        if ($attributes['category'] === RoleCategory::Terrain->value && ! in_array('departures.view', $permissions, true)) {
            abort(422, 'La permission departures.view est obligatoire pour un rôle terrain.');
        }

        $code = $this->uniqueCode((string) $attributes['label']);

        $role = DB::transaction(function () use ($attributes, $code, $permissions): Role {
            $role = Role::query()->create([
                'name' => $code,
                'guard_name' => 'web',
                'is_system' => false,
                'label' => $attributes['label'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'],
                'is_active' => true,
            ]);

            $role->syncPermissions($permissions);

            return $role->load('permissions');
        });

        activity('identity-access')
            ->performedOn($role)
            ->causedBy($actor)
            ->withProperties([
                'role_code' => $role->name,
                'category' => $role->category->value,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ])
            ->log('role_created');

        return $role;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function permissions(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        $invalid = array_values(array_diff($permissions, PermissionCatalog::codes()));

        abort_if($invalid !== [], 422, 'La matrice contient une permission inconnue.');

        return $permissions;
    }

    private function uniqueCode(string $label): string
    {
        $slug = Str::slug($label, '_');
        $base = 'custom_'.($slug !== '' ? $slug : 'role');
        $code = $base;
        $suffix = 2;

        while (Role::query()->where('name', $code)->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
