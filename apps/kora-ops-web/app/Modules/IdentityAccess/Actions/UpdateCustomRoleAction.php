<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Models\Role;

class UpdateCustomRoleAction
{
    /**
     * @param  array{label: string, description: string|null, category: string}  $attributes
     */
    public function execute(Role $role, array $attributes, mixed $actor): Role
    {
        abort_if($role->is_system, 422, 'Les rôles système ne peuvent pas être renommés.');

        $category = RoleCategory::from($attributes['category']);
        $assignedUsers = $role->users();

        if (in_array($category, [RoleCategory::Agency, RoleCategory::Terrain], true)) {
            abort_if($assignedUsers->whereNull('agency_id')->exists(), 422, 'Tous les utilisateurs de ce rôle doivent être rattachés à une agence.');
        }

        if ($category === RoleCategory::Central) {
            abort_if($assignedUsers->whereNotNull('agency_id')->exists(), 422, 'Un rôle central ne peut pas être attribué à une agence.');
        }

        if ($category === RoleCategory::Terrain) {
            abort_if(! $role->permissions()->where('name', 'departures.view')->exists(), 422, 'La permission departures.view est obligatoire pour un rôle terrain.');
        }

        $before = $role->only(['label', 'description', 'category', 'is_active']);
        $role->forceFill($attributes)->save();
        $role->refresh()->load('permissions');

        activity('identity-access')
            ->performedOn($role)
            ->causedBy($actor)
            ->withProperties(['before' => $before, 'after' => $role->only(['label', 'description', 'category', 'is_active'])])
            ->log('role_updated');

        return $role;
    }
}
