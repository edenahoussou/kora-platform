<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Actions\CreateCustomRoleAction;
use App\Modules\IdentityAccess\Actions\DeactivateRoleAction;
use App\Modules\IdentityAccess\Actions\RestoreRoleAction;
use App\Modules\IdentityAccess\Actions\SyncRolePermissionsAction;
use App\Modules\IdentityAccess\Actions\UpdateCustomRoleAction;
use App\Modules\IdentityAccess\Data\PermissionCatalog;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Http\Requests\StoreRoleRequest;
use App\Modules\IdentityAccess\Http\Requests\SyncRolePermissionsRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateRoleRequest;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('category')
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role): array => $this->serializeRole($role))
            ->values()
            ->all();

        $permissions = collect(PermissionCatalog::definitions())
            ->groupBy('domain')
            ->map(fn ($items, string $domain): array => [
                'domain' => $domain,
                'label' => PermissionCatalog::domainLabel($domain),
                'items' => $items->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('roles', [
            'roles' => $roles,
            'categories' => array_map(fn (RoleCategory $category): array => [
                'value' => $category->value,
                'label' => $category->translatedLabel(),
            ], RoleCategory::cases()),
            'permissions' => $permissions,
            'canManage' => Gate::allows('create', Role::class),
        ]);
    }

    public function store(StoreRoleRequest $request, CreateCustomRoleAction $createRole): RedirectResponse
    {
        $createRole->execute($request->payload(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rôle personnalisé créé.')]);

        return to_route('roles');
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateCustomRoleAction $updateRole): RedirectResponse
    {
        $updateRole->execute($role, [
            'label' => $request->string('label')->trim()->value(),
            'description' => $request->string('description')->trim()->value() ?: null,
            'category' => $request->string('category')->trim()->value(),
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rôle mis à jour.')]);

        return back();
    }

    public function updatePermissions(
        SyncRolePermissionsRequest $request,
        Role $role,
        SyncRolePermissionsAction $syncPermissions,
    ): RedirectResponse {
        $syncPermissions->execute($role, $request->permissions(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions du rôle mises à jour.')]);

        return back();
    }

    public function destroy(Role $role, DeactivateRoleAction $deactivateRole): RedirectResponse
    {
        Gate::authorize('delete', $role);
        $deactivateRole->execute($role, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rôle désactivé.')]);

        return back();
    }

    public function restore(Role $role, RestoreRoleAction $restoreRole): RedirectResponse
    {
        Gate::authorize('restore', $role);
        $restoreRole->execute($role, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rôle réactivé.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRole(Role $role): array
    {
        return [
            'code' => $role->name,
            'label' => SystemRole::tryFrom($role->name)?->translatedLabel()
                ?? $role->label
                ?? $role->name,
            'description' => $role->description,
            'category' => $role->category->value,
            'isSystem' => (bool) $role->is_system,
            'isActive' => (bool) $role->is_active,
            'permissions' => $role->permissions
                ->sortBy('name')
                ->map(fn ($permission): array => [
                    'code' => $permission->name,
                    'label' => PermissionCatalog::label($permission->name),
                    'domain' => (string) str($permission->name)->before('.'),
                ])
                ->values()
                ->all(),
        ];
    }
}
