<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MEZ-150 — permissions de l'écran « Versions de l'application ».
 * Réservées à `system_admin` (même règle que `KoraBusRolePermissionSeeder`,
 * qui donne à ce rôle `SystemRole::allPermissions()`).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['app-releases.view', 'app-releases.manage'];

    /** @var list<string> */
    private array $roles = ['system_admin'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions)
            ->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roles)
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get();

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roles)
            ->each(fn (Role $role): mixed => $role->revokePermissionTo($permissions));

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
