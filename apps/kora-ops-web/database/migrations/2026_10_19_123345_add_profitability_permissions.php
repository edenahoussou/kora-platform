<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(['profitability.view', 'profitability.export'])
            ->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['daf', 'general_management', 'operations_director', 'commercial_director', 'system_admin'])
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['profitability.view', 'profitability.export'])
            ->get();

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['daf', 'general_management', 'operations_director', 'commercial_director', 'system_admin'])
            ->each(fn (Role $role): mixed => $role->revokePermissionTo($permissions));

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['profitability.view', 'profitability.export'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
