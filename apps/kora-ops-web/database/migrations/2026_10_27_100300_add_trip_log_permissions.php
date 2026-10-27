<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Carnet de bord OPS Mobile — saisie du kilométrage, du carburant, des
 * péages et taxes frontières. Accordée au chauffeur et au convoyeur
 * (`system_admin` reçoit toutes les permissions, voir
 * KoraBusRolePermissionSeeder).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['trip-logs.create'];

    /** @var list<string> */
    private array $roles = ['bus_driver', 'conductor', 'system_admin'];

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
